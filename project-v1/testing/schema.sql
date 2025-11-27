-- SafeZone Emergency Alert System Database Schema
-- Run this script in your MySQL database first!

CREATE DATABASE IF NOT EXISTS safezone_poc;
USE safezone_poc;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (phone_number)
);

-- Incidents table
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    gps_lat DECIMAL(10, 8) NOT NULL,
    gps_lng DECIMAL(11, 8) NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    latest_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    sender ENUM('user', 'dispatcher') NOT NULL,
    message_text TEXT,
    media_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_incident_id (incident_id),
    INDEX idx_created_at (created_at)
);

-- Sample data for testing
INSERT IGNORE INTO users (full_name, phone_number) VALUES 
('John Smith', '+1234567890'),
('Sarah Johnson', '+1987654321'),
('Mike Chen', '+1555666777');

-- Test data - recent incident
INSERT IGNORE INTO incidents (user_id, gps_lat, gps_lng, status, latest_message) VALUES 
(1, 37.7749, -122.4194, 'active', 'Test emergency alert'),
(2, 37.7849, -122.4094, 'active', 'Help needed at library');

INSERT IGNORE INTO messages (incident_id, sender, message_text) VALUES 
(1, 'user', 'EMERGENCY: I need immediate assistance!'),
(1, 'dispatcher', 'Help is on the way. Stay calm.'),
(2, 'user', 'There is a medical emergency here');