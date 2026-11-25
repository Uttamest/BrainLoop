CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(80) NOT NULL,
 email VARCHAR(254) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 description TEXT NOT NULL DEFAULT (''),
 subject VARCHAR(80) NOT NULL DEFAULT '',
 color CHAR(7) NOT NULL DEFAULT '#6C5CE7',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_decks_owner (user_id, updated_at),
 CONSTRAINT fk_decks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flashcards (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 deck_id BIGINT UNSIGNED NOT NULL,
 question TEXT NOT NULL,
 answer TEXT NOT NULL,
 explanation TEXT NOT NULL DEFAULT (''),
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_cards_deck (deck_id),
 CONSTRAINT fk_cards_deck FOREIGN KEY (deck_id) REFERENCES decks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS card_progress (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 flashcard_id BIGINT UNSIGNED NOT NULL,
 review_state VARCHAR(16) NOT NULL DEFAULT 'new',
 due_at DATETIME NOT NULL,
 interval_days DOUBLE NOT NULL DEFAULT 0,
 ease_factor DECIMAL(4,2) NOT NULL DEFAULT 2.5,
 repetition_count INT UNSIGNED NOT NULL DEFAULT 0,
 total_reviews BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_rating VARCHAR(8) DEFAULT NULL,
 last_reviewed_at DATETIME DEFAULT NULL,
 UNIQUE KEY uq_progress_card (flashcard_id),
 KEY idx_progress_due (user_id, due_at),
 CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_progress_card FOREIGN KEY (flashcard_id) REFERENCES flashcards(id) ON DELETE CASCADE,
 CONSTRAINT chk_progress_state CHECK (review_state IN ('new','relearning','review')),
 CONSTRAINT chk_progress_interval CHECK (interval_days >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 flashcard_id BIGINT UNSIGNED NOT NULL,
 rating VARCHAR(8) NOT NULL,
 interval_before DOUBLE NOT NULL,
 interval_after DOUBLE NOT NULL,
 reviewed_at DATETIME NOT NULL,
 is_practice TINYINT UNSIGNED NOT NULL DEFAULT 0,
 submission_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 UNIQUE KEY uq_review_submission (submission_key),
 KEY idx_history_owner_date (user_id, reviewed_at),
 KEY idx_history_card (flashcard_id),
 CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_history_card FOREIGN KEY (flashcard_id) REFERENCES flashcards(id) ON DELETE CASCADE,
 CONSTRAINT chk_history_rating CHECK (rating IN ('again','hard','good','easy')),
 CONSTRAINT chk_history_practice CHECK (is_practice IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identifier CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 attempted_at BIGINT UNSIGNED NOT NULL,
 KEY idx_attempt_identifier (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
