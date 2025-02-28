<?php

namespace dev_t0r\bids_rtc\signaling;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

Constants::__init__();

final class Constants
{
	public static function __init__()
	{
		self::$UUID_NULL = Uuid::fromString(Uuid::NIL);
	}

	public const HTTP_OK = 200;
	public const HTTP_CREATED = 201;
	public const HTTP_ACCEPTED = 202;
	public const HTTP_NO_CONTENT = 204;

	public const HTTP_MOVED_PERMANENTLY = 301;
	public const HTTP_FOUND = 302;
	public const HTTP_NOT_MODIFIED = 304;

	public const HTTP_BAD_REQUEST = 400;
	public const HTTP_UNAUTHORIZED = 401;
	public const HTTP_FORBIDDEN = 403;
	public const HTTP_NOT_FOUND = 404;
	public const HTTP_METHOD_NOT_ALLOWED = 405;
	public const HTTP_NOT_ACCEPTABLE = 406;
	public const HTTP_CONFLICT = 409;
	public const HTTP_PRECONDITION_FAILED = 412;
	public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;

	public const HTTP_INTERNAL_SERVER_ERROR = 500;
	public const HTTP_NOT_IMPLEMENTED = 501;
	public const HTTP_BAD_GATEWAY = 502;
	public const HTTP_SERVICE_UNAVAILABLE = 503;
	public const HTTP_GATEWAY_TIMEOUT = 504;
	public const HTTP_VERSION_NOT_SUPPORTED = 505;

	private static $UUID_NULL = null;
	public static function getUuidNull(): UuidInterface
	{
		return self::$UUID_NULL;
	}
	public const UID_ANONYMOUS = '';

	public const PAGE_MIN_VALUE = 1;
	public const PAGE_DEFAULT_VALUE = 1;
	public const PER_PAGE_DEFAULT_VALUE = 10;
	public const PER_PAGE_MIN_VALUE = 5;
	public const PER_PAGE_MAX_VALUE = 100;

	public const DESCRIPTION_MIN_LENGTH = 0;
	public const DESCRIPTION_MAX_LENGTH = 255;
	public const NAME_MIN_LENGTH = 1;
	public const NAME_MAX_LENGTH = 255;

	public const BULK_INSERT_MAX_COUNT = 100;

	public const HEADER_TOTAL_COUNT = 'X-Total-Count';

	public const string ROLE_ADMIN = 'admin';
}
