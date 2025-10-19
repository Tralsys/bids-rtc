<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Utils;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * 管理者APIコントローラー
 */
class AdminController
{
	private string $logDir;

	private const array ALLOWED_LOG_FILES = [
		'app.log',
		'slim-app.log',
		'access.log',
		'error.log',
		'php-error.log',
	];

	public function __construct(
		private readonly LoggerInterface $logger,
		array $config,
	) {
		$this->logDir = $config['admin.logs_dir'] ?? __DIR__ . '/../../logs';
	}

	/**
	 * ログファイル一覧を取得
	 */
	#[OA\Get(
		path: '/admin/logs',
		operationId: 'getLogsList',
		summary: 'ログファイル一覧を取得',
		security: [['bearerAuth' => []]],
		tags: ['Admin']
	)]
	#[OA\Response(
		response: 200,
		description: 'ログファイル一覧',
		content: new OA\JsonContent(
			type: 'object',
			properties: [
				new OA\Property(
					property: 'logs',
					type: 'array',
					items: new OA\Items(
						type: 'object',
						properties: [
							new OA\Property(property: 'name', type: 'string'),
							new OA\Property(property: 'size', type: 'integer'),
							new OA\Property(property: 'modified', type: 'string'),
						],
					),
				),
			],
		)
	)]
	#[OA\Response(
		response: 403,
		description: '権限エラー',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	public function getLogsList(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		if (!Utils::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden: Admin role required');
		}

		try {
			$logFiles = [];
			$logDir = realpath($this->logDir);

			if ($logDir === false || !is_dir($logDir)) {
				return Utils::withError($response, 500, 'Log directory not found');
			}

			$files = scandir($logDir);
			if ($files === false) {
				return Utils::withError($response, 500, 'Failed to read log directory');
			}

			foreach ($files as $file) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				$filePath = $logDir . DIRECTORY_SEPARATOR . $file;

				if (is_file($filePath) && $this->isAllowedLogFile($file)) {
					$size = filesize($filePath);
					$mtime = filemtime($filePath);

					$logFiles[] = [
						'name' => $file,
						'size' => $size !== false ? $size : 0,
						'modified' => $mtime !== false ? date('c', $mtime) : null,
					];
				}
			}

			// 更新日時の降順でソート
			usort($logFiles, function ($a, $b) {
				return strcmp($b['modified'] ?? '', $a['modified'] ?? '');
			});

			return Utils::withJson($response, ['logs' => $logFiles]);
		} catch (\Exception $e) {
			$this->logger->error('Failed to get logs list: ' . $e->getMessage());
			return Utils::withError($response, 500, 'Internal server error');
		}
	}

	/**
	 * ログファイルの内容を取得
	 */
	#[OA\Get(
		path: '/admin/logs/{filename}',
		operationId: 'getLogContent',
		summary: 'ログファイルの内容を取得',
		security: [['bearerAuth' => []]],
		tags: ['Admin']
	)]
	#[OA\Parameter(
		name: 'filename',
		in: 'path',
		required: true,
		description: 'ログファイル名',
		schema: new OA\Schema(type: 'string')
	)]
	#[OA\Response(
		response: 200,
		description: 'ログファイル内容',
		content: new OA\JsonContent(
			type: 'object',
			properties: [
				new OA\Property(property: 'filename', type: 'string'),
				new OA\Property(property: 'content', type: 'string'),
				new OA\Property(property: 'lines', type: 'integer'),
			],
		)
	)]
	#[OA\Response(
		response: 403,
		description: '権限エラー',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	#[OA\Response(
		response: 404,
		description: 'ファイルが見つかりません',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	public function getLogContent(
		ServerRequestInterface $request,
		ResponseInterface $response,
		array $args,
	): ResponseInterface {
		if (!Utils::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden: Admin role required');
		}

		$filename = $args['filename'] ?? '';

		// セキュリティチェック: ディレクトリトラバーサル防止
		if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
			return Utils::withError($response, 400, 'Invalid filename');
		}

		if (!$this->isAllowedLogFile($filename)) {
			return Utils::withError($response, 403, 'Access to this file is not allowed');
		}

		try {
			$logDir = realpath($this->logDir);
			if ($logDir === false) {
				return Utils::withError($response, 500, 'Log directory not found');
			}

			$filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

			if (!file_exists($filePath) || !is_file($filePath)) {
				return Utils::withError($response, 404, 'Log file not found');
			}

			$content = file_get_contents($filePath);
			if ($content === false) {
				return Utils::withError($response, 500, 'Failed to read log file');
			}

			$lines = substr_count($content, "\n");

			return Utils::withJson($response, [
				'filename' => $filename,
				'content' => $content,
				'lines' => $lines,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Failed to get log content: ' . $e->getMessage());
			return Utils::withError($response, 500, 'Internal server error');
		}
	}

	/**
	 * ファイルが許可されたログファイルかチェック
	 */
	private function isAllowedLogFile(string $filename): bool
	{
		// 完全一致チェック
		if (in_array($filename, self::ALLOWED_LOG_FILES, true)) {
			return true;
		}

		// 日付付きログファイルのパターンマッチ
		// 例: app.log.2025-10-19
		foreach (self::ALLOWED_LOG_FILES as $allowedFile) {
			if (str_starts_with($filename, $allowedFile . '.')) {
				// 日付形式のチェック (YYYY-MM-DD)
				$suffix = substr($filename, strlen($allowedFile) + 1);
				if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $suffix)) {
					return true;
				}
			}
		}

		return false;
	}
}
