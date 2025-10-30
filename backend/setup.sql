-- backend/setup.sql
CREATE TABLE IF NOT EXISTS lottery_draws (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lottery_type VARCHAR(255) NOT NULL,
    issue_number VARCHAR(255) NOT NULL,
    draw_date DATE NOT NULL,
    numbers TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
