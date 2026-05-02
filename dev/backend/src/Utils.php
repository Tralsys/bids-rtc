<?php

declare(strict_types=1);

namespace BidsRtc\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

Utils::init();

/**
 * ユーティリティ関数
 */
final class Utils
{
  private static \DateTimeZone $UTC;

  public static function init(): void
  {
    self::$UTC = new \DateTimeZone('UTC');
  }

  public static function getUTC(): \DateTimeZone
  {
    return self::$UTC;
  }

  public static function getUtcNow(): \DateTime
  {
    return new \DateTime('now', self::$UTC);
  }

  /**
   * リクエストからユーザーIDを取得する（認証されていない場合はnull）
   */
  public static function getUserIdOrNull(ServerRequestInterface $request): ?string
  {
    return $request->getAttribute(Constants::ATTR_NAME_UID);
  }

  /**
   * リクエストからユーザーIDを取得する（認証されていない場合は匿名）
   */
  public static function getUserIdOrAnonymous(ServerRequestInterface $request): string
  {
    return self::getUserIdOrNull($request) ?? Constants::UID_ANONYMOUS;
  }

  /**
   * リクエストが管理者権限を持つか確認する
   */
  public static function getIsAdminRole(ServerRequestInterface $request): bool
  {
    $role = $request->getAttribute(Constants::ATTR_NAME_USER_ROLE);
    return $role === Constants::ROLE_ADMIN;
  }

  /**
   * JSONレスポンスを返す
   */
  public static function withJson(
    ResponseInterface $response,
    mixed $data,
    int $statusCode = 200,
  ): ResponseInterface {
    $response = $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($statusCode);
    $response->getBody()->write(json_encode($data));
    return $response;
  }

  /**
   * エラーレスポンスを返す
   */
  public static function withError(
    ResponseInterface $response,
    int $statusCode,
    string $message,
    ?int $errorCode = null,
  ): ResponseInterface {
    $errorCode ??= $statusCode;

    return self::withJson(
      $response,
      [
        'error' => [
          'code' => $errorCode,
          'message' => $message,
        ],
      ],
      $statusCode,
    );
  }

  /**
   * UUIDエラーレスポンスを返す
   */
  public static function withUuidError(ResponseInterface $response): ResponseInterface
  {
    return self::withError($response, 400, 'Invalid UUID format');
  }

  /**
   * 認証エラーレスポンスを返す
   */
  public static function withUnauthorizedError(ResponseInterface $response): ResponseInterface
  {
    return self::withError($response, 401, 'Unauthorized');
  }

  /**
   * ユーザーIDをハッシュ化
   */
  public static function getHashedUserId(string $userId): string
  {
    return hash('sha256', $userId);
  }

  /**
   * X-Client-Id ヘッダから UUID を取得する（無効または存在しない場合は null）
   */
  public static function getClientIdFromHeaderOrNull(ServerRequestInterface $request): ?UuidInterface
  {
    $headerValue = $request->getHeaderLine(Constants::HEADER_CLIENT_ID);
    if ($headerValue === '') {
      return null;
    }
    if (!Uuid::isValid($headerValue)) {
      return null;
    }
    try {
      return Uuid::fromString($headerValue);
    } catch (\Throwable) {
      return null;
    }
  }

  /**
   * X-Client-Id ヘッダが不正な場合の 400 エラーレスポンスを返す
   */
  public static function withHeaderClientIdError(ResponseInterface $response): ResponseInterface
  {
    return self::withError($response, 400, 'Invalid X-Client-Id header');
  }

  /**
   * DB の DATETIME(6) 形式文字列を DateTime オブジェクトに変換する
   * "Y-m-d H:i:s.u" および "Y-m-d H:i:s" の両形式に対応。タイムゾーンは UTC。
   */
  public static function dbDateStrToDateTime(string $dateStr): \DateTime
  {
    $tz = self::$UTC;
    $dt = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dateStr, $tz);
    if ($dt !== false) {
      return $dt;
    }
    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, $tz);
    if ($dt !== false) {
      return $dt;
    }
    return new \DateTime($dateStr, $tz);
  }

  /**
   * バイナリ 16 バイトの UUID をパースする（null または 16 バイト以外は null を返す）
   */
  public static function uuidFromBytesOrNull(?string $bytes): ?UuidInterface
  {
    if ($bytes === null || strlen($bytes) !== 16) {
      return null;
    }
    try {
      return Uuid::fromBytes($bytes);
    } catch (\Throwable) {
      return null;
    }
  }
}
