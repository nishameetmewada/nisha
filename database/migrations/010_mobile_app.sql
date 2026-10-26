-- Native app access is separate from browser sessions. No existing records change.
CREATE TABLE IF NOT EXISTS mobile_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    auth_version BIGINT UNSIGNED NOT NULL,
    device_name VARCHAR(80) NOT NULL DEFAULT 'NISHA app',
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    UNIQUE KEY mobile_token_hash (token_hash),
    KEY mobile_tokens_user (user_id,revoked_at,expires_at),
    CONSTRAINT mobile_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mobile_auth_limits (
    scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_bucket BIGINT UNSIGNED NOT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope_hash,window_bucket),
    KEY mobile_limits_window (window_bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requests are fulfilled by the owner. Financial retention is not hard deletion.
CREATE TABLE IF NOT EXISTS mobile_deletion_requests (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    status ENUM('pending','in_progress','completed','declined') NOT NULL DEFAULT 'pending',
    reason VARCHAR(500) NOT NULL DEFAULT '',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    owner_note VARCHAR(1000) NOT NULL DEFAULT '',
    CONSTRAINT mobile_deletion_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
