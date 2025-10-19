<?php

declare(strict_types=1);

namespace BidsRtc\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
}
