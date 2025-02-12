<?php

/**
 * BIDS WebRTC Signaling API
 * PHP version 7.4
 *
 * @package dev_t0r\bids_rtc\signaling
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */

/**
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://github.com/openapitools/openapi-generator.git
 */

/**
 * NOTE: This class is auto generated by the openapi generator program.
 * https://github.com/openapitools/openapi-generator
 */
namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\BaseModel;

/**
 * PostSDPOfferInfoRequestBody
 *
 * @package dev_t0r\bids_rtc\signaling\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class PostSDPOfferInfoRequestBody extends BaseModel
{
    /**
     * @var string Models namespace.
     * Can be required for data deserialization when model contains referenced schemas.
     */
    protected const MODELS_NAMESPACE = '\dev_t0r\bids_rtc\signaling\model';

    /**
     * @var string Constant with OAS schema of current class.
     * Should be overwritten by inherited class.
     */
    protected const MODEL_SCHEMA = <<<'SCHEMA'
{
  "title" : "SDP OfferInfo POST Request Body",
  "required" : [ "client_id", "offer", "role" ],
  "type" : "object",
  "properties" : {
    "client_id" : {
      "$ref" : "#/components/schemas/offer_client_id"
    },
    "role" : {
      "$ref" : "#/components/schemas/offer_client_role"
    },
    "offer" : {
      "$ref" : "#/components/schemas/offer"
    },
    "established_clients" : {
      "type" : "array",
      "description" : "既に接続が確立されているクライアントのIDのリスト\n",
      "items" : {
        "type" : "string",
        "format" : "uuid"
      }
    }
  }
}
SCHEMA;
}
