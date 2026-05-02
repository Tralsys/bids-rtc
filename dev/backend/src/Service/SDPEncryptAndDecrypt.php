<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service;

/**
 * SDP の暗号化・復号ユーティリティ
 *
 * AES-256-CBC を使用し、鍵はユーザー ID の SHA-256 ハッシュ (raw 32 バイト) とする。
 * IV はランダム 16 バイトを生成し、暗号文の先頭に付与する。
 */
class SDPEncryptAndDecrypt
{
	private const string ENCRYPT_METHOD = 'AES-256-CBC';

	// `openssl_cipher_key_length($ENCRYPT_METHOD)` の結果 (32 バイト)
	private const int ENCRYPT_KEY_LENGTH = 32;

	// `openssl_cipher_iv_length($ENCRYPT_METHOD)` の結果 (16 バイト)
	private const int ENCRYPT_IV_LENGTH = 16;

	private const int ENCRYPT_OPTS = OPENSSL_RAW_DATA;

	/**
	 * rawUserId から暗号化鍵を導出する
	 *
	 * SHA-256 の raw 出力が ENCRYPT_KEY_LENGTH バイトになる。
	 */
	private function deriveKey(string $rawUserId): string
	{
		return hash('sha256', $rawUserId, true);
	}

	/**
	 * 平文を暗号化して IV + 暗号文のバイト列を返す
	 *
	 * @param string $rawUserId 暗号化鍵の導出に使うユーザー ID (raw)
	 * @param string $plain     暗号化する平文
	 * @return string           IV (16 バイト) + 暗号文 の生バイト列
	 */
	public function encrypt(string $rawUserId, string $plain): string
	{
		$key = $this->deriveKey($rawUserId);
		$iv = openssl_random_pseudo_bytes(self::ENCRYPT_IV_LENGTH);
		$encrypted = openssl_encrypt(
			$plain,
			self::ENCRYPT_METHOD,
			$key,
			self::ENCRYPT_OPTS,
			$iv,
		);
		// IV を先頭に付与して返す
		return $iv . $encrypted;
	}

	/**
	 * 暗号化バイト列を復号して平文を返す
	 *
	 * @param string $rawUserId  暗号化鍵の導出に使うユーザー ID (raw)
	 * @param string $protected  IV (16 バイト) + 暗号文 の生バイト列
	 * @return string|null       復号された平文。失敗した場合は null
	 */
	public function decrypt(string $rawUserId, string $protected): ?string
	{
		$key = $this->deriveKey($rawUserId);
		$iv = substr($protected, 0, self::ENCRYPT_IV_LENGTH);
		$encrypted = substr($protected, self::ENCRYPT_IV_LENGTH);
		$result = openssl_decrypt(
			$encrypted,
			self::ENCRYPT_METHOD,
			$key,
			self::ENCRYPT_OPTS,
			$iv,
		);
		// openssl_decrypt は失敗時に false を返す
		return $result === false ? null : $result;
	}
}
