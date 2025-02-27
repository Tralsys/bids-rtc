<?php

namespace dev_t0r\bids_rtc\signaling\auth;

use dev_t0r\bids_rtc\signaling\Constants;
use dev_t0r\bids_rtc\signaling\Utils;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class MyAuthMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly MyAuthUtil $authUtil,
		private readonly LoggerInterface $logger,
		private readonly ResponseFactoryInterface $responseFactory,
	) {
	}

	private const int MAX_REQ_PER_SEC = 20;
	private const int MAX_REQ_PER_MIN = 100;
	private const int MAX_REQ_PER_10MIN = 500;
	private const int MAX_REQ_PER_HOUR = 1000;
	private const int MAX_REQ_LIMIT_DATA_COUNT = self::MAX_REQ_PER_HOUR * 2;
	private const int MAX_REQ_LIMIT_DATA_SHRINK_TO = self::MAX_REQ_PER_HOUR;

	private const int REQ_LIMIT_DATA_BYTES = 8;
	private const int MAX_REQ_PER_SEC_BYTES = self::MAX_REQ_PER_SEC * self::REQ_LIMIT_DATA_BYTES;
	private const int MAX_REQ_PER_MIN_BYTES = self::MAX_REQ_PER_MIN * self::REQ_LIMIT_DATA_BYTES;
	private const int MAX_REQ_PER_10MIN_BYTES = self::MAX_REQ_PER_10MIN * self::REQ_LIMIT_DATA_BYTES;
	private const int MAX_REQ_PER_HOUR_BYTES = self::MAX_REQ_PER_HOUR * self::REQ_LIMIT_DATA_BYTES;
	private const int MAX_REQ_LIMIT_DATA_BYTES = self::MAX_REQ_LIMIT_DATA_COUNT * self::REQ_LIMIT_DATA_BYTES;
	private const int REQ_LIMIT_DATA_SHRINK_TO_BYTES = self::MAX_REQ_LIMIT_DATA_SHRINK_TO * self::REQ_LIMIT_DATA_BYTES;

	public const string ATTR_NAME_UID = 'tokenObj';
	public const string ATTR_NAME_CLIENT_ID = 'tokenObj';

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		$tokenHeader = $request->getHeader('Authorization');
		$uid = null;
		if ($tokenHeader == null || count($tokenHeader) == 0) {
			$this->logger->info("Token was not set");
		} else {
			$tokenStr = $request->getHeader('Authorization')[0];
			if ($tokenStr != null && preg_match('/^Bearer\s+(.*)$/', $tokenStr, $matches)) {
				$tokenStr = $matches[1];
				$parseTokenResult = $this->authUtil->parse($tokenStr);
				if (!$parseTokenResult->error != null) {
					return $parseTokenResult->error->getResponseWithJson($this->responseFactory->createResponse());
				}

				$request = $request
					->withAttribute($this::ATTR_NAME_UID, $parseTokenResult->uid)
					->withAttribute($this::ATTR_NAME_CLIENT_ID, $parseTokenResult->clientId);
			} else {
				return Utils::withError($this->responseFactory->createResponse(), 400, 'Invalid token format');
			}
		}

		if ($uid != null) {
			// 認証なしでできることは限られているため、認証なしはレートリミットをかけない
			$response = $this->processRateLimit($uid);
			if ($response != null) {
				return $response;
			}
		}

		$response = $handler->handle($request);

		return $response;
	}

	private function processRateLimit(
		string $uid,
	): ?ResponseInterface {
		$hashedUid = sha1($uid);
		$rateLimitKey = "$hashedUid.rate_limit";
		$filePath = __DIR__ . '/../../access/' . $rateLimitKey;
		$fp = fopen($filePath, 'c+');
		if ($fp === false) {
			$err = error_get_last();
			$this->logger->error("Failed to open file: {path} / {err}", ['path' => $filePath, 'err' => $err]);
			return Utils::withError($this->responseFactory->createResponse(), 500, 'Failed to Process Request');
		}
		try {
			if (!flock($fp, LOCK_EX)) {
				$this->logger->error("Failed to lock file: {path}", ['path' => $filePath]);
				return Utils::withError($this->responseFactory->createResponse(), 500, 'Failed to Process Request');
			}

			$fileSize = filesize($filePath);
			if ($fileSize < 0) {
				$this->logger->error("Failed to get file size: {path}", ['path' => $filePath]);
				return Utils::withError($this->responseFactory->createResponse(), 500, 'Failed to Process Request (System is Broken. Please contact the administrator.)');
			} else if ($fileSize == false) {
				$fileSize = 0;
			}

			$fileData = null;
			if (self::MAX_REQ_LIMIT_DATA_BYTES < $fileSize) {
				$this->logger->info("Shrink rate limit data: {path}", ['path' => $filePath]);
				fseek($fp, -self::REQ_LIMIT_DATA_SHRINK_TO_BYTES, SEEK_END);
				$fileData = fread($fp, self::REQ_LIMIT_DATA_SHRINK_TO_BYTES);
				ftruncate($fp, 0);
				rewind($fp);
				fwrite($fp, $fileData);
				$fileSize = self::REQ_LIMIT_DATA_SHRINK_TO_BYTES;
			}
			$now = microtime(true);
			// e: double(maybe 8byte / little endian)
			$nowBytes = pack('e', $now);
			fseek($fp, $fileSize, SEEK_SET);
			fwrite($fp, $nowBytes);
			fflush($fp);
			$fileSize += self::REQ_LIMIT_DATA_BYTES;
			if ($fileData != null) {
				$fileData .= $nowBytes;
			}
			clearstatcache(true, $filePath);

			if ($fileSize < self::MAX_REQ_PER_SEC_BYTES) {
				$this->logger->debug("Rate limit data is not enough: {path}", ['path' => $filePath]);
				return null;
			}
			$secCheck = self::getTimeValue($fp, self::MAX_REQ_PER_SEC_BYTES, 0, $fileSize, $fileData);
			if ($now - $secCheck < 1) {
				$this->logger->info("Rate limit (sec) exceeded: {path}", ['path' => $filePath]);
				return Utils::withRateLimitError($this->responseFactory->createResponse(), $now - $secCheck);
			}

			if ($fileSize < self::MAX_REQ_PER_MIN_BYTES) {
				$this->logger->debug("Rate limit data is not enough: {path}", ['path' => $filePath]);
				return null;
			}
			$minCheck = self::getTimeValue($fp, self::MAX_REQ_PER_MIN_BYTES, self::MAX_REQ_PER_SEC_BYTES, $fileSize, $fileData);
			if ($now - $minCheck < 60) {
				$this->logger->info("Rate limit (min) exceeded: {path}", ['path' => $filePath]);
				return Utils::withRateLimitError($this->responseFactory->createResponse(), $now - $minCheck);
			}

			if ($fileSize < self::MAX_REQ_PER_10MIN_BYTES) {
				$this->logger->debug("Rate limit data is not enough: {path}", ['path' => $filePath]);
				return null;
			}
			$min10Check = self::getTimeValue($fp, self::MAX_REQ_PER_10MIN_BYTES, self::MAX_REQ_PER_MIN_BYTES, $fileSize, $fileData);
			if ($now - $min10Check < 600) {
				$this->logger->info("Rate limit (10min) exceeded: {path}", ['path' => $filePath]);
				return Utils::withRateLimitError($this->responseFactory->createResponse(), $now - $min10Check);
			}

			if ($fileSize < self::MAX_REQ_PER_HOUR_BYTES) {
				$this->logger->debug("Rate limit data is not enough: {path}", ['path' => $filePath]);
				return null;
			}
			$hourCheck = self::getTimeValue($fp, self::MAX_REQ_PER_HOUR_BYTES, self::MAX_REQ_PER_10MIN_BYTES, $fileSize, $fileData);
			if ($now - $hourCheck < 3600) {
				$this->logger->info("Rate limit (hour) exceeded: {path}", ['path' => $filePath]);
				return Utils::withRateLimitError($this->responseFactory->createResponse(), $now - $hourCheck);
			}

			$this->logger->debug("Rate limit passed: {path}", ['path' => $filePath]);
			return null;
		} catch (\Exception $e) {
			$this->logger->error("Failed to process rate limit: {message}", ['message' => $e->getMessage()]);
			return Utils::withError($this->responseFactory->createResponse(), 500, 'Failed to Process Request');
		} finally {
			fclose($fp);
		}
	}

	/** @param resource $fp */
	private static function getTimeValue(
		$fp,
		int $offsetFromEndBytes,
		int $lastOffsetFromEndBytes,
		int $fileSizeBytes,
		?string $fileData,
	): float {
		if ($fileData != null) {
			$unpackResult = unpack('e', $fileData, $fileSizeBytes + $offsetFromEndBytes);
			return $unpackResult[1];
		}
		fseek($fp, $offsetFromEndBytes - $lastOffsetFromEndBytes, SEEK_SET);
		$bytes = fread($fp, self::REQ_LIMIT_DATA_BYTES);
		$unpackResult = unpack('e', $bytes);
		return $unpackResult[1];
	}

	public static function getUserIdOrNull(
		ServerRequestInterface $request,
	): ?string {
		return $request->getAttribute(self::ATTR_NAME_UID);
	}
	public static function getUserIdOrAnonymous(
		ServerRequestInterface $request,
	): string {
		return self::getUserIdOrNull($request) ?? Constants::UID_ANONYMOUS;
	}
}

