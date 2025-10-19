<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use Ramsey\Uuid\UuidInterface;

/**
 * データベースのクライアント情報
 */
class DbClientInfo
{
  public function __construct(
    public readonly UuidInterface $client_id,
    public readonly UuidInterface $app_id,
    public readonly string $name,
    public readonly \DateTime $created_at,
  ) {
  }

  /**
   * APIレスポンス用のClientInfoに変換
   */
  public function toApiClientInfo(): ClientInfo
  {
    return new ClientInfo(
      app_id: $this->app_id->toString(),
      client_id: $this->client_id->toString(),
      name: $this->name,
      created_at: $this->created_at->format(\DateTimeInterface::ATOM),
    );
  }

  /**
   * トークン付きClientInfoに変換
   */
  public function toApiClientInfoWithToken(string $refreshToken): ClientInfoWithToken
  {
    return new ClientInfoWithToken(
      client_info: $this->toApiClientInfo(),
      client_token: $refreshToken,
    );
  }
}
