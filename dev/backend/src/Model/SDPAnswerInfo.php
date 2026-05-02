<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Answer情報
 *
 * POST 入力時は {sdp_id, answer} のみ、
 * GET レスポンス時は {sdp_id, answer_client_id, answer} の三つを持つ。
 * answer_client_id が null の場合は JSON 出力に含めない。
 */
#[OA\Schema(
  schema: 'SDPAnswerInfo',
  title: 'SDPAnswerInfo',
  description: 'SDP Answer情報',
  type: 'object',
  required: ['sdp_id', 'answer']
)]
class SDPAnswerInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'sdp_id',
      description: 'SDP交換ID',
      type: 'string',
      format: 'uuid'
    )]
    public readonly string $sdp_id,

    #[OA\Property(
      property: 'answer',
      description: 'SDP Answer（Base64エンコード済み）',
      type: 'string',
      format: 'byte'
    )]
    public readonly string $answer,

    #[OA\Property(
      property: 'answer_client_id',
      description: 'Answerを送信したクライアントID',
      type: 'string',
      readOnly: true,
      nullable: true
    )]
    public readonly ?string $answer_client_id = null,
  ) {
  }

  /**
   * POST 入力用ファクトリ (answer_client_id なし)
   */
  public static function fromPostInput(string $sdpId, string $answer): self
  {
    return new self(
      sdp_id: $sdpId,
      answer: $answer,
    );
  }

  /**
   * DB 取得結果用ファクトリ (answer_client_id あり)
   */
  public static function fromDb(
    string $sdpId,
    string $answerClientId,
    string $answer,
  ): self {
    return new self(
      sdp_id: $sdpId,
      answer: $answer,
      answer_client_id: $answerClientId,
    );
  }

  public function jsonSerialize(): array
  {
    $data = [
      'sdp_id' => $this->sdp_id,
      'answer' => $this->answer,
    ];
    if ($this->answer_client_id !== null) {
      $data['answer_client_id'] = $this->answer_client_id;
    }
    return $data;
  }
}
