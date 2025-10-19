<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use Ramsey\Uuid\UuidInterface;

/**
 * データベースのアプリケーション情報
 */
class DbAppInfo
{
  public function __construct(
    public readonly UuidInterface $app_id,
    public readonly string $name,
    public readonly string $description,
    public readonly string $owner,
    public readonly \DateTime $created_at,
  ) {
  }

  /**
   * APIレスポンス用のApplicationInfoに変換
   */
  public function toApiAppInfo(): ApplicationInfo
  {
    return new ApplicationInfo(
      app_id: $this->app_id->toString(),
      name: $this->name,
      description: $this->description,
      owner: $this->owner,
      created_at: $this->created_at->format(\DateTimeInterface::ATOM),
    );
  }
}
