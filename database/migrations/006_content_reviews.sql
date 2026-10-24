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
