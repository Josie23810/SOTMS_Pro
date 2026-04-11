CREATE TABLE IF NOT EXISTS payment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    event_note TEXT NULL,
    event_data JSON NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_events_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_events_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_events_lookup (payment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_verification_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_user_id INT NOT NULL,
    admin_user_id INT NULL,
    decision VARCHAR(40) NOT NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tutor_verification_reviews_tutor FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_verification_reviews_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tutor_verification_reviews_lookup (tutor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS provider VARCHAR(30) DEFAULT 'manual',
    ADD COLUMN IF NOT EXISTS tracking_id VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS pesapal_txn_id VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS paypal_payment_id VARCHAR(100) NULL;

INSERT INTO schema_migrations (migration_key)
VALUES ('003_phase4_operations_workflow')
ON DUPLICATE KEY UPDATE migration_key = migration_key;
