-- Create Database
CREATE DATABASE IF NOT EXISTS fingerprint_system;
USE fingerprint_system;

-- Create Table for Scan Logs
CREATE TABLE IF NOT EXISTS scan_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(255) NOT NULL,
    scan_type ENUM('Check-In', 'Check-Out') NOT NULL,
    scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8)
);

-- Sample Data (Optional)
INSERT INTO scan_logs (user_name, scan_type, scan_time, latitude, longitude) 
VALUES 
('John Doe', 'Check-In', '2025-02-24 08:30:00', 11.55089, 104.91219),
('Jane Smith', 'Check-Out', '2025-02-24 17:45:00', 11.55089, 104.91219);
