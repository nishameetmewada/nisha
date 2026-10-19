-- Select your Hostinger MySQL database in phpMyAdmin before importing this file.
-- Import once; existing accounts and practice results are preserved.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    exam ENUM('IELTS', 'PTE') NOT NULL,
    skill ENUM('Reading', 'Listening', 'Writing', 'Speaking') NOT NULL,
    score SMALLINT UNSIGNED NOT NULL,
    total SMALLINT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attempts_user_created (user_id, created_at),
    CONSTRAINT attempts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add full mock histories without modifying existing accounts or short attempts.
-- Select your existing NISHA database before importing. Safe to import again.
CREATE TABLE IF NOT EXISTS exam_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    exam_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_name VARCHAR(150) NOT NULL,
    exam VARCHAR(10) NOT NULL,
    responses_json LONGTEXT NOT NULL,
    result_json LONGTEXT NOT NULL,
    objective_score INT UNSIGNED NOT NULL,
    objective_total INT UNSIGNED NOT NULL,
    elapsed_seconds INT UNSIGNED NOT NULL,
    assisted TINYINT UNSIGNED NOT NULL DEFAULT 0,
    completed_sections_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY exam_runs_user_attempt (user_id, attempt_id),
    KEY exam_runs_user_created (user_id, created_at),
    CONSTRAINT exam_runs_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
