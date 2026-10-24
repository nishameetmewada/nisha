-- Add commerce without replacing existing accounts, exams or AI history.
CREATE TABLE IF NOT EXISTS commerce_settings (
    id TINYINT UNSIGNED NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    access_days SMALLINT UNSIGNED NOT NULL,
    checkout_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT commerce_settings_owner_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_settings_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    access_days SMALLINT UNSIGNED NOT NULL,
    checkout_enabled TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT commerce_settings_audit_owner_fk FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_trials (
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('available','reserved','consumed') NOT NULL DEFAULT 'available',
    exam_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempt_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reserved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT commerce_trials_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mock_access_grants (
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind ENUM('trial','paid') NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    PRIMARY KEY (user_id,attempt_id),
    KEY mock_access_user_exam (user_id,exam_id),
    CONSTRAINT mock_access_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_orders (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    access_days SMALLINT UNSIGNED NOT NULL,
    checkout_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    payment_intent VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    checkout_url TEXT NULL,
    status ENUM('pending','paid','partially_refunded','refunded','disputed') NOT NULL DEFAULT 'pending',
    refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY payment_orders_checkout (checkout_id),
    UNIQUE KEY payment_orders_intent (payment_intent),
    KEY payment_orders_user_created (user_id,created_at),
    CONSTRAINT payment_orders_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    starts_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY student_entitlement_order (order_id),
    KEY student_entitlement_active (user_id,revoked_at,expires_at),
    CONSTRAINT student_entitlement_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT student_entitlement_order_fk FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Funding identity distinguishes normal expiry grace from refunds/disputes.
CREATE TABLE IF NOT EXISTS mock_paid_sources (
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (user_id,attempt_id),
    CONSTRAINT mock_paid_source_grant_fk FOREIGN KEY (user_id,attempt_id) REFERENCES mock_access_grants(user_id,attempt_id) ON DELETE CASCADE,
    CONSTRAINT mock_paid_source_order_fk FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_events (
    event_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_type VARCHAR(100) CHARACTER SET ascii NOT NULL,
    processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_refunds (
    refund_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (refund_id),
    KEY commerce_refund_order (order_id),
    CONSTRAINT commerce_refund_order_fk FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
