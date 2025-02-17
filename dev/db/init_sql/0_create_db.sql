CREATE TABLE
	`applications`
(
	`app_id`
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v7'
	,

	`name`
		VARCHAR(255)
		NOT NULL
	,

	`description`
		TEXT(65535)
		NOT NULL
	,

	`owner`
		VARCHAR(255)
		NOT NULL
	,

	`default_role`
		VARCHAR(255)
		CHARACTER SET ascii
		COLLATE ascii_bin
		NOT NULL
	,

	`created_at`
		DATETIME
		NOT NULL
		DEFAULT CURRENT_TIMESTAMP
	,

	`deleted_at`
		DATETIME
		DEFAULT NULL
	,

	PRIMARY KEY (
		`app_id`
	)
);

CREATE TABLE
	`clients`
(
	-- Hashed
	`user_id`
		VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
		NOT NULL
	,

	`client_id`
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v7'
	,

	`app_id`
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v7'
	,

	`name`
		VARCHAR(255)
		NOT NULL
	,

	`created_at`
		DATETIME
		NOT NULL
		DEFAULT CURRENT_TIMESTAMP
	,

	`deleted_at`
		DATETIME
		DEFAULT NULL
	,

	`last_used_at`
		DATETIME
		NOT NULL
		DEFAULT CURRENT_TIMESTAMP
	,

	PRIMARY KEY (
		`user_id`,
		`client_id`
	),

	FOREIGN KEY (
		`app_id`
	) REFERENCES
		`applications` (
			`app_id`
	)
);

CREATE TABLE
	`sdp`
(
	`sdp_id`
		BINARY(16)
		NOT NULL
		COMMENT 'UUID v7'
	,

	-- Hashed
	`user_id`
		VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
		NOT NULL
	,

	`offer_client_id`
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v7'
	,

	`role`
		VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
		NOT NULL
	,

	`answer_client_id`
    BINARY(16)
		DEFAULT NULL
    COMMENT 'UUID v7'
	,

	-- Protected with user_id(Raw)
	`offer`
		BLOB(65535)
		NOT NULL
	,

	-- Protected with user_id(Raw)
	`answer`
		BLOB(65535)
		DEFAULT NULL
	,

	`error_message`
		VARCHAR(255)
		DEFAULT NULL
	,

	`created_at`
		DATETIME(6)
		NOT NULL
		DEFAULT CURRENT_TIMESTAMP(6)
	,

	`deleted_at`
		DATETIME(6)
		DEFAULT NULL
	,

	PRIMARY KEY (
		`sdp_id`
	)

	-- TODO: 一旦クライアントIDはクライアント側で生成するようにする
	-- FOREIGN KEY (
	-- 	`offer_client_id`
	-- ) REFERENCES
	-- 	`clients` (
	-- 		`client_id`
	-- ),
	-- FOREIGN KEY (
	-- 	`answer_client_id`
	-- ) REFERENCES
	-- 	`clients` (
	-- 		`client_id`
	-- )
);
