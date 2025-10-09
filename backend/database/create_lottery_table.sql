CREATE TABLE IF NOT EXISTS lottery_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_number VARCHAR(255) NOT NULL,
    winning_numbers VARCHAR(255) NOT NULL,
    drawing_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);