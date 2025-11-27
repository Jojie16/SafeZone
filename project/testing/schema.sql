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

ALTER TABLE incidents 
ADD COLUMN gps_accuracy FLOAT DEFAULT 0,
ADD COLUMN location_method VARCHAR(50) DEFAULT 'gps',
ADD COLUMN altitude FLOAT NULL,
ADD COLUMN heading FLOAT NULL,
ADD COLUMN speed FLOAT NULL,
ADD COLUMN location_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;



-- V2 Schema




-- SafeZone Basic Schema - Minimal setup
CREATE DATABASE IF NOT EXISTS safezone_poc;
USE safezone_poc;

-- Essential tables only
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (phone_number)
);

CREATE TABLE incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gps_lat DECIMAL(10, 8) NOT NULL,
    gps_lng DECIMAL(11, 8) NOT NULL,
    gps_accuracy FLOAT DEFAULT 0,
    location_method VARCHAR(50) DEFAULT 'gps',
    status ENUM('active', 'closed') DEFAULT 'active',
    latest_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    sender ENUM('user', 'dispatcher') NOT NULL,
    message_text TEXT,
    media_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id)
);
