-- Permitir mensajes sin cuenta registrada.
ALTER TABLE chat_messages
    MODIFY user_id BIGINT UNSIGNED NULL,
    ADD COLUMN guest_id CHAR(64) NULL AFTER user_id,
    ADD COLUMN guest_name VARCHAR(32) NULL AFTER guest_id,
    ADD KEY idx_chat_messages_guest (guest_id, created_at);

-- Límites persistentes para visitantes.
CREATE TABLE chat_guest_limits (
    guest_id CHAR(64) NOT NULL,
    message_date DATE NOT NULL,
    message_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_message_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (guest_id),
    KEY idx_chat_guest_limits_date (message_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
