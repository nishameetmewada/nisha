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

-- Platform upgrade: 004_commerce.sql
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

-- Platform upgrade: 005_teachers.sql
-- Add teacher moderation, qualified viewing and capped profit-share ledgers.
-- Select the existing NISHA database. Existing accounts/results are preserved.
CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('teacher','admin') NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT user_roles_assigner_fk FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    bio TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    review_note VARCHAR(1000) NOT NULL DEFAULT '',
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT teacher_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT teacher_profiles_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coaching_videos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    video_url VARCHAR(2000) NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    course_id VARCHAR(24) NOT NULL,
    skill VARCHAR(12) NOT NULL,
    status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
    review_note VARCHAR(1000) NOT NULL DEFAULT '',
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY coaching_videos_teacher(teacher_id,created_at),
    KEY coaching_videos_catalog(status,course_id,skill),
    CONSTRAINT coaching_videos_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT coaching_videos_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coaching_watch_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    video_id BIGINT UNSIGNED NOT NULL,
    period CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    video_revision INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    watched_seconds DECIMAL(10,3) UNSIGNED NOT NULL DEFAULT 0,
    watched_ranges_json TEXT NULL,
    last_position DECIMAL(10,3) UNSIGNED NOT NULL DEFAULT 0,
    last_seen DATETIME(6) NOT NULL,
    qualified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY coaching_watch_unique(user_id,video_id,period,video_revision),
    KEY coaching_watch_period(period,qualified_at,video_id),
    CONSTRAINT coaching_watch_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT coaching_watch_video_fk FOREIGN KEY(video_id) REFERENCES coaching_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coaching_watch_presence (
    user_id BIGINT UNSIGNED NOT NULL,
    video_id BIGINT UNSIGNED NOT NULL,
    video_revision INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY(user_id),
    CONSTRAINT coaching_presence_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT coaching_presence_video_fk FOREIGN KEY(video_id) REFERENCES coaching_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qualification is unique per lesson/student/month even after a lesson revision.
CREATE TABLE IF NOT EXISTS coaching_qualified_views (
    user_id BIGINT UNSIGNED NOT NULL,
    video_id BIGINT UNSIGNED NOT NULL,
    period CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    video_revision INT UNSIGNED NOT NULL,
    qualified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,video_id,period),
    KEY coaching_qualified_teacher_period(teacher_id,period),
    CONSTRAINT coaching_qualified_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT coaching_qualified_video_fk FOREIGN KEY(video_id) REFERENCES coaching_videos(id) ON DELETE CASCADE,
    CONSTRAINT coaching_qualified_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_policy_settings (
    id TINYINT UNSIGNED NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    watch_percent TINYINT UNSIGNED NOT NULL DEFAULT 70,
    paid_students_only TINYINT UNSIGNED NOT NULL DEFAULT 1,
    currency CHAR(3) CHARACTER SET ascii NOT NULL DEFAULT 'AUD',
    video_hosts_json TEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    CONSTRAINT teacher_policy_updater_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_reward_locks (
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    PRIMARY KEY(currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_profit_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    revenue_cents BIGINT UNSIGNED NOT NULL,
    refunds_cents BIGINT UNSIGNED NOT NULL,
    expenses_cents BIGINT UNSIGNED NOT NULL,
    carried_loss_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    remaining_loss_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    net_profit_cents BIGINT NOT NULL,
    pool_cents BIGINT UNSIGNED NOT NULL,
    share_basis_points SMALLINT UNSIGNED NOT NULL,
    verification_note VARCHAR(2000) NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    status ENUM('approved') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY teacher_profit_unique(period,currency),
    CONSTRAINT teacher_profit_approver_fk FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_reward_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    qualified_views INT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    status ENUM('allocated','paid') NOT NULL DEFAULT 'allocated',
    payout_reference VARCHAR(200) NULL,
    paid_by BIGINT UNSIGNED NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY teacher_reward_unique(period_id,teacher_id),
    CONSTRAINT teacher_reward_period_fk FOREIGN KEY(period_id) REFERENCES teacher_profit_periods(id) ON DELETE RESTRICT,
    CONSTRAINT teacher_reward_teacher_fk FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT teacher_reward_payer_fk FOREIGN KEY(paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform upgrade: 006_content_reviews.sql
-- Teacher-reviewed publishing is independent of generated draft availability.
-- Existing questions, accounts and reports are retained.
CREATE TABLE IF NOT EXISTS content_review_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind ENUM('question','mock') NOT NULL,
    course_id VARCHAR(24) CHARACTER SET ascii NOT NULL,
    draft_number SMALLINT UNSIGNED NOT NULL,
    content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    semantic_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json LONGTEXT NOT NULL,
    reviewer_id BIGINT UNSIGNED NULL,
    review_note VARCHAR(2000) NOT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY content_review_history(content_id,id),
    CONSTRAINT content_review_teacher_fk FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_publications (
    content_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind ENUM('question','mock') NOT NULL,
    course_id VARCHAR(24) CHARACTER SET ascii NOT NULL,
    draft_number SMALLINT UNSIGNED NOT NULL,
    version_id BIGINT UNSIGNED NOT NULL,
    semantic_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('published','withdrawn') NOT NULL DEFAULT 'published',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(content_id),
    UNIQUE KEY content_unique_semantics(kind,semantic_hash),
    KEY content_public_catalog(kind,course_id,status,draft_number),
    CONSTRAINT content_public_version_fk FOREIGN KEY(version_id) REFERENCES content_review_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_mock_dependencies (
    mock_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_version_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(mock_id,question_id),
    CONSTRAINT content_dependency_mock_fk FOREIGN KEY(mock_id) REFERENCES content_publications(content_id) ON DELETE CASCADE,
    CONSTRAINT content_dependency_question_fk FOREIGN KEY(question_version_id) REFERENCES content_review_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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


-- Additive website settings. No student, payment or reward records are replaced.
CREATE TABLE IF NOT EXISTS site_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    support_email VARCHAR(254) NOT NULL DEFAULT '',
    home_tagline VARCHAR(180) NOT NULL DEFAULT 'Your next chapter starts here.',
    announcement_text VARCHAR(280) NOT NULL DEFAULT '',
    announcement_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_site_settings_owner FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    settings_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_site_settings_audit_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
