<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

/**
 * SDP ロール定数クラス
 */
final class SdpRoles
{
  public const string PROVIDER = 'provider';
  public const string SUBSCRIBER = 'subscriber';

  /**
   * ロールに対応するターゲットロールを返す
   *
   * provider → subscriber、subscriber → provider を返す。
   * 不正なロールの場合は \InvalidArgumentException をスローする。
   */
  public static function targetOf(string $role): string
  {
    return match ($role) {
      self::PROVIDER => self::SUBSCRIBER,
      self::SUBSCRIBER => self::PROVIDER,
      default => throw new \InvalidArgumentException("Invalid role: $role"),
    };
  }

  /**
   * ロールが有効かどうかを返す
   */
  public static function isValid(string $role): bool
  {
    return $role === self::PROVIDER || $role === self::SUBSCRIBER;
  }

  private function __construct()
  {
    // インスタンス化禁止
  }
}
