<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Answer情報
 */
#[OA\Schema(
  schema: 'SDPAnswerInfo',
  title: 'SDPAnswerInfo',
  description: 'SDP Answer情報',
  type: 'object',
  required: ['sdp_id', 'answer_client_id', 'answer']
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
      property: 'answer_client_id',
      description: 'Answerを送信するクライアントID',
      type: 'string',
      format: 'uuid'
    )]
    public readonly string $answer_client_id,

    #[OA\Property(
      property: 'answer',
      description: 'SDP Answer（Base64エンコード済み）',
      type: 'string'
    )]
    public readonly string $answer,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'sdp_id' => $this->sdp_id,
      'answer_client_id' => $this->answer_client_id,
      'answer' => $this->answer,
    ];
  }
}
