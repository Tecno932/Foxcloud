CREATE TABLE IF NOT EXISTS discord_accounts (
    user_id BIGINT UNSIGNED NOT NULL,
    discord_user_id VARCHAR(20) NOT NULL,
    username VARCHAR(100) NOT NULL,
    global_name VARCHAR(100) DEFAULT NULL,
    avatar_hash VARCHAR(100) DEFAULT NULL,
    linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),
    UNIQUE KEY uq_discord_accounts_discord_user_id (discord_user_id),

    CONSTRAINT fk_discord_accounts_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;