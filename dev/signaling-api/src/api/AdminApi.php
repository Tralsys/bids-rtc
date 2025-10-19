<?php

namespace dev_t0r\bids_rtc\signaling\api;

use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
use dev_t0r\bids_rtc\signaling\Utils;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * AdminApi
 * 管理者用API
 */
class AdminApi
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
	 * GET /admin/logs
	 * ログファイルの一覧を取得
	 */
	public function getLogsList(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		if (!MyAuthMiddleware::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden: Admin role required');
		}

		try {
			$logFiles = [];
			$logDir = realpath($this->logDir);

			if ($logDir === false || !is_dir($logDir)) {
				return Utils::withError($response, 500, 'Log directory not found');
			}

			// ディレクトリ内のファイルを走査
			$files = scandir($logDir);
			if ($files === false) {
				return Utils::withError($response, 500, 'Failed to read log directory');
			}

			foreach ($files as $file) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				$filePath = $logDir . DIRECTORY_SEPARATOR . $file;

				// 通常のファイルかつ、許可されたログファイルのパターンに一致するか確認
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
	 * GET /admin/logs/{filename}
	 * 特定のログファイルの内容を取得
	 */
	public function getLogContent(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $filename,
	): ResponseInterface {
		if (!MyAuthMiddleware::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden: Admin role required');
		}

		try {
			// ファイル名のバリデーション（ディレクトリトラバーサル対策）
			if (!$this->isAllowedLogFile($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
				return Utils::withError($response, 400, 'Invalid filename');
			}

			$logDir = realpath($this->logDir);
			if ($logDir === false) {
				return Utils::withError($response, 500, 'Log directory not found');
			}

			$filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

			// ファイルの存在確認とパストラバーサル対策
			$realFilePath = realpath($filePath);
			if ($realFilePath === false || strpos($realFilePath, $logDir) !== 0) {
				return Utils::withError($response, 404, 'Log file not found');
			}

			if (!is_file($realFilePath)) {
				return Utils::withError($response, 404, 'Log file not found');
			}

			// クエリパラメータから取得行数を指定できるようにする
			$queryParams = $request->getQueryParams();
			$lines = isset($queryParams['lines']) ? (int) $queryParams['lines'] : 1000;
			$lines = max(1, min($lines, 10000)); // 1〜10000行に制限

			// ファイルの最後のN行を取得
			$content = $this->readLastLines($realFilePath, $lines);

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
	 * ファイル名が許可されたログファイルのパターンに一致するか確認
	 */
	private function isAllowedLogFile(string $filename): bool
	{
		// 基本的な許可リスト
		foreach (self::ALLOWED_LOG_FILES as $allowedFile) {
			if ($filename === $allowedFile) {
				return true;
			}
		}

		// 日付付きログファイル（例: app.2025-02-16.log）
		if (preg_match('/^(app|slim-app)\.\d{4}-\d{2}-\d{2}\.log$/', $filename)) {
			return true;
		}

		return false;
	}

	/**
	 * ファイルの最後のN行を取得
	 */
	private function readLastLines(string $filePath, int $lines): string
	{
		$file = new \SplFileObject($filePath, 'r');
		$file->seek(PHP_INT_MAX);
		$lastLine = $file->key();

		$startLine = max(0, $lastLine - $lines);

		$result = [];
		$file->seek($startLine);

		while (!$file->eof()) {
			$line = $file->current();
			if ($line !== false) {
				$result[] = rtrim($line, "\n\r");
			}
			$file->next();
		}

		return implode("\n", $result);
	}
}
