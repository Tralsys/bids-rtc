<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

/**
 * Answer 登録リクエスト 1 件分の入力 DTO
 */
class SdpIdAndAnswer
{
  public function __construct(
    public readonly string $sdp_id,
    public readonly string $answer,
  ) {
  }

  /**
   * 連想配列から生成するファクトリ
   *
   * @param array<string, mixed> $data
   */
  public static function fromArray(array $data): self
  {
    return new self(
      sdp_id: (string) ($data['sdp_id'] ?? ''),
      answer: (string) ($data['answer'] ?? ''),
    );
  }
}
