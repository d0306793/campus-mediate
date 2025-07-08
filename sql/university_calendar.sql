-- Create university_calendar table
CREATE TABLE IF NOT EXISTS university_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample semester data
INSERT INTO university_calendar (semester_name, start_date, end_date, is_active) VALUES
('Fall 2023', '2023-09-01', '2023-12-15', 0),
('Spring 2024', '2024-01-15', '2024-05-10', 0),
('Summer 2024', '2024-06-01', '2024-08-15', 0),
('Fall 2024', '2024-09-01', '2024-12-15', 1);