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
