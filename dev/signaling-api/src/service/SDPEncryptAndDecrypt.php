<?php

namespace dev_t0r\bids_rtc\signaling\service;

class SDPEncryptAndDecrypt
{
	public function __construct(
		private readonly string $rawUserId,
	) {
	}

	private const string ENCRYPT_METHOD = 'AES-256-CBC';
	// `openssl_cipher_key_length($ENCRYPT_METHOD)` の結果
	private const int ENCRYPT_KEY_LENGTH = 32;
	// `openssl_cipher_iv_length($ENCRYPT_METHOD)` の結果
	private const int ENCRYPT_IV_LENGTH = 16;
	private const int ENCRYPT_OPTS = OPENSSL_RAW_DATA;

	private ?string $encryptKey = null;
	private function getEncryptKey(): string
	{
		if ($this->encryptKey == null) {
			// 結果が`ENCRYPT_KEY_LENGTH`バイトになるようにハッシュ化
			$this->encryptKey = hash('sha256', $this->rawUserId, true);
		}
		return $this->encryptKey;
	}
	public function encrypt(
		string $rawSdp,
	): string {
		$key = $this->getEncryptKey();
		$iv = openssl_random_pseudo_bytes(self::ENCRYPT_IV_LENGTH);
		$encrypted = openssl_encrypt(
			$rawSdp,
			self::ENCRYPT_METHOD,
			$key,
			self::ENCRYPT_OPTS,
			$iv,
		);
		return "$iv$encrypted";
	}
	public function decrypt(
		string $encryptedSdp,
	): string {
		$key = $this->getEncryptKey();
		$iv = substr($encryptedSdp, 0, self::ENCRYPT_IV_LENGTH);
		$encrypted = substr($encryptedSdp, self::ENCRYPT_IV_LENGTH);
		return openssl_decrypt(
			$encrypted,
			self::ENCRYPT_METHOD,
			$key,
			self::ENCRYPT_OPTS,
			$iv,
		);
	}
}
