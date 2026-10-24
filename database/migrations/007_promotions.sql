-- Optional owner promotions. Existing order amounts and paid access are unchanged.
CREATE TABLE IF NOT EXISTS commerce_promotions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    public_label VARCHAR(120) NOT NULL DEFAULT '',
    discount_type ENUM('percent','fixed') NOT NULL,
    discount_value BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    max_redemptions BIGINT UNSIGNED NULL,
    max_per_student BIGINT UNSIGNED NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    featured TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY commerce_promotion_code (code),
    CONSTRAINT commerce_promotion_owner_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable checkout price snapshot; state changes only on signed terminal events.
-- Reserved checkouts count towards usage caps until Stripe confirms expiry/failure.
CREATE TABLE IF NOT EXISTS commerce_promotion_orders (
    order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    promotion_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    public_label VARCHAR(120) NOT NULL DEFAULT '',
    base_amount_minor BIGINT UNSIGNED NOT NULL,
    discount_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    access_days SMALLINT UNSIGNED NOT NULL,
    status ENUM('reserved','redeemed','released') NOT NULL DEFAULT 'reserved',
    release_reason VARCHAR(40) CHARACTER SET ascii NOT NULL DEFAULT '',
    reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    redeemed_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    PRIMARY KEY (order_id),
    KEY commerce_promotion_usage (promotion_id,status,user_id),
    CONSTRAINT commerce_promotion_order_fk FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE CASCADE,
    CONSTRAINT commerce_promotion_order_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT commerce_promotion_order_promotion_fk FOREIGN KEY (promotion_id) REFERENCES commerce_promotions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_promotions_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    promotion_id BIGINT UNSIGNED NOT NULL,
    owner_id BIGINT UNSIGNED NULL,
    action VARCHAR(30) CHARACTER SET ascii NOT NULL,
    settings_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT commerce_promotion_audit_promotion_fk FOREIGN KEY (promotion_id) REFERENCES commerce_promotions(id) ON DELETE RESTRICT,
    CONSTRAINT commerce_promotion_audit_owner_fk FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
