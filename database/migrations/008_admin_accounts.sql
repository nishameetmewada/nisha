-- Account administration is additive: preserve accounts, results and ledgers.
CREATE TABLE IF NOT EXISTS account_security (
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    auth_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reauth_failures TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reauth_blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id),
    CONSTRAINT account_security_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_account_locks (
    id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_account_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) CHARACTER SET ascii NOT NULL,
    reason VARCHAR(500) NOT NULL,
    before_json TEXT NOT NULL,
    after_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY admin_account_audit_target(target_id,id),
    KEY admin_account_audit_actor(actor_id,id),
    CONSTRAINT admin_account_audit_actor_fk FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT admin_account_audit_target_fk FOREIGN KEY(target_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
