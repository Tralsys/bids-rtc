<?php

declare(strict_types=1);

namespace BidsRtc\Backend;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

Constants::init();

/**
 * アプリケーション全体で使用する定数
 */
final class Constants
{
  private static UuidInterface $UUID_NULL;

  public static function init(): void
  {
    self::$UUID_NULL = Uuid::fromString(Uuid::NIL);
  }

  // HTTP Status Codes
  public const HTTP_OK = 200;
  public const HTTP_CREATED = 201;
  public const HTTP_ACCEPTED = 202;
  public const HTTP_NO_CONTENT = 204;

  public const HTTP_BAD_REQUEST = 400;
  public const HTTP_UNAUTHORIZED = 401;
  public const HTTP_FORBIDDEN = 403;
  public const HTTP_NOT_FOUND = 404;
  public const HTTP_CONFLICT = 409;

  public const HTTP_INTERNAL_SERVER_ERROR = 500;

  public static function getUuidNull(): UuidInterface
  {
    return self::$UUID_NULL;
  }

  public const UID_ANONYMOUS = '';

  // User Roles
  public const ROLE_ADMIN = 'admin';

  // Request Attribute Names (set by middleware)
  public const ATTR_NAME_UID = 'uid';
  public const ATTR_NAME_CLIENT_ID = 'clientId';
  public const ATTR_NAME_USER_ROLE = 'userRole';
}
