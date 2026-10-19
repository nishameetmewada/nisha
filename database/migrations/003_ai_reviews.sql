-- Select the existing NISHA database first. Preserves accounts and exam reports.
CREATE TABLE IF NOT EXISTS ai_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    request_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    item_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_name VARCHAR(180) NOT NULL,
    task_type VARCHAR(150) NOT NULL,
    mode VARCHAR(24) CHARACTER SET ascii NOT NULL,
    response_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    cache_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    claim_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('pending','completed','failed') NOT NULL,
    response_text MEDIUMTEXT NOT NULL,
    review_json MEDIUMTEXT NULL,
    error_code VARCHAR(80) NULL,
    claimed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ai_reviews_request (user_id, request_id),
    KEY ai_reviews_cache (user_id, cache_key, status),
    KEY ai_reviews_attempt (user_id, attempt_id),
    KEY ai_reviews_history (user_id, created_at),
    CONSTRAINT ai_reviews_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Both rows are locked in one transaction before a paid provider attempt.
CREATE TABLE IF NOT EXISTS ai_review_usage (
    usage_day DATE NOT NULL,
    scope_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (usage_day, scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
