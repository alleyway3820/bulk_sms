-- Complete Database schema for BulkVS Portal
-- Create these tables in your MySQL database

CREATE DATABASE IF NOT EXISTS bulkvs_portal;
USE bulkvs_portal;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Phone numbers table
CREATE TABLE phone_numbers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    number VARCHAR(20) UNIQUE NOT NULL,
    friendly_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User phone permissions table
CREATE TABLE user_phone_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone_number_id INT NOT NULL,
    can_send BOOLEAN DEFAULT TRUE,
    can_receive BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (phone_number_id) REFERENCES phone_numbers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_phone (user_id, phone_number_id)
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    message_body TEXT NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    bulkvs_message_id VARCHAR(100),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_to_number (to_number),
    INDEX idx_from_number (from_number),
    INDEX idx_created_at (created_at),
    INDEX idx_direction (direction),
    INDEX idx_status (status)
);

-- API settings table
CREATE TABLE api_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_username VARCHAR(255) NOT NULL,
    api_password VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User sessions table (for real-time features)
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    socket_id VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_socket_id (socket_id)
);

-- Activity log table (for audit trail)
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Notifications table (for real-time notifications)
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Message delivery status table (for tracking message delivery)
CREATE TABLE message_delivery_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    status ENUM('queued', 'sending', 'sent', 'delivered', 'failed', 'undelivered') NOT NULL,
    status_details TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_id (message_id),
    INDEX idx_status (status)
);

-- Insert default admin user (password: admin123)
-- You should change this password after first login!
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@yourdomain.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default API settings (replace with your actual BulkVS credentials)
INSERT INTO api_settings (api_username, api_password, webhook_url) VALUES 
('your_api_username', 'your_api_password', 'https://yourdomain.com/webhook.php');

-- Create indexes for better performance
CREATE INDEX idx_messages_conversation ON messages (from_number, to_number, created_at);
CREATE INDEX idx_messages_user_phone ON messages (user_id, to_number, from_number);
CREATE INDEX idx_phone_numbers_active ON phone_numbers (is_active);
CREATE INDEX idx_users_active ON users (is_active);

-- Create a view for easier message querying
CREATE VIEW message_conversations AS
SELECT 
    CASE 
        WHEN m.direction = 'inbound' THEN m.from_number
        ELSE m.to_number 
    END as contact_number,
    CASE 
        WHEN m.direction = 'inbound' THEN m.to_number
        ELSE m.from_number 
    END as user_number,
    MAX(m.created_at) as last_message_time,
    COUNT(*) as message_count,
    MAX(m.id) as last_message_id,
    m.user_id
FROM messages m
GROUP BY contact_number, user_number, m.user_id
ORDER BY last_message_time DESC;

-- Create a view for user statistics
CREATE VIEW user_message_stats AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(m.id) as total_messages,
    SUM(CASE WHEN m.direction = 'inbound' THEN 1 ELSE 0 END) as inbound_messages,
    SUM(CASE WHEN m.direction = 'outbound' THEN 1 ELSE 0 END) as outbound_messages,
    SUM(CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as messages_today,
    SUM(CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as messages_this_week,
    MAX(m.created_at) as last_activity
FROM users u
LEFT JOIN messages m ON u.id = m.user_id
WHERE u.is_active = 1
GROUP BY u.id, u.username;

-- Sample data for testing (optional - remove in production)
-- Add some sample phone numbers
INSERT INTO phone_numbers (number, friendly_name) VALUES 
('15551234567', 'Main Support Line'),
('15559876543', 'Sales Line'),
('15555551234', 'Marketing Line');

-- Grant permissions to admin user for all phone numbers
INSERT INTO user_phone_permissions (user_id, phone_number_id, can_send, can_receive)
SELECT 1, id, TRUE, TRUE FROM phone_numbers;

-- Add some sample activity log entries
INSERT INTO activity_log (user_id, action, details, ip_address) VALUES
(1, 'login', 'First login after setup', '127.0.0.1'),
(1, 'api_settings_updated', 'Initial API configuration', '127.0.0.1'),
(1, 'phone_numbers_added', 'Added sample phone numbers', '127.0.0.1');

-- Create stored procedures for common operations

-- Procedure to get user conversation list
DELIMITER //
CREATE PROCEDURE GetUserConversations(IN user_id INT, IN phone_number VARCHAR(20))
BEGIN
    SELECT DISTINCT 
        CASE 
            WHEN m.direction = 'inbound' THEN m.from_number
            ELSE m.to_number 
        END as contact_number,
        MAX(m.created_at) as last_message_time,
        COUNT(*) as message_count,
        (SELECT message_body FROM messages m2 
         WHERE ((m2.from_number = contact_number AND m2.to_number = phone_number) 
                OR (m2.to_number = contact_number AND m2.from_number = phone_number))
         ORDER BY m2.created_at DESC LIMIT 1) as last_message
    FROM messages m 
    JOIN user_phone_permissions upp ON (
        (m.to_number = phone_number OR m.from_number = phone_number) AND
        upp.user_id = user_id AND
        upp.phone_number_id = (SELECT id FROM phone_numbers WHERE number = phone_number)
    )
    WHERE (m.to_number = phone_number OR m.from_number = phone_number)
    GROUP BY contact_number
    ORDER BY last_message_time DESC;
END //

-- Procedure to get conversation messages
CREATE PROCEDURE GetConversationMessages(
    IN user_id INT, 
    IN phone_number VARCHAR(20), 
    IN contact_number VARCHAR(20),
    IN message_limit INT
)
BEGIN
    SELECT m.*, pn.friendly_name
    FROM messages m
    LEFT JOIN phone_numbers pn ON (m.to_number = pn.number OR m.from_number = pn.number)
    JOIN user_phone_permissions upp ON (
        pn.id = upp.phone_number_id AND 
        upp.user_id = user_id
    )
    WHERE ((m.from_number = contact_number AND m.to_number = phone_number) OR 
           (m.from_number = phone_number AND m.to_number = contact_number))
    ORDER BY m.created_at ASC
    LIMIT message_limit;
END //

-- Procedure to log user activity
CREATE PROCEDURE LogUserActivity(
    IN user_id INT,
    IN action_type VARCHAR(100),
    IN action_details TEXT,
    IN user_ip VARCHAR(45)
)
BEGIN
    INSERT INTO activity_log (user_id, action, details, ip_address)
    VALUES (user_id, action_type, action_details, user_ip);
END //

-- Procedure to clean old data
CREATE PROCEDURE CleanOldData()
BEGIN
    -- Clean old activity logs (keep last 6 months)
    DELETE FROM activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    -- Clean old notifications (keep last 3 months)
    DELETE FROM notifications 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    
    -- Clean old inactive sessions (keep last 1 week)
    DELETE FROM user_sessions 
    WHERE is_active = FALSE AND last_activity < DATE_SUB(NOW(), INTERVAL 1 WEEK);
    
    -- Clean old message delivery status (keep last 1 month)
    DELETE FROM message_delivery_status 
    WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 MONTH);
END //

DELIMITER ;

-- Create triggers for automatic logging

-- Trigger to log user logins
DELIMITER //
CREATE TRIGGER user_login_log 
AFTER UPDATE ON users 
FOR EACH ROW
BEGIN
    IF NEW.updated_at != OLD.updated_at THEN
        INSERT INTO activity_log (user_id, action, details, ip_address)
        VALUES (NEW.id, 'profile_updated', 'User profile modified', 
                COALESCE(@user_ip, 'unknown'));
    END IF;
END //

-- Trigger to update message delivery status
CREATE TRIGGER message_status_update 
AFTER UPDATE ON messages 
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO message_delivery_status (message_id, status, status_details)
        VALUES (NEW.id, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END //

DELIMITER ;

-- Create events for automatic maintenance (requires EVENT_SCHEDULER=ON)
-- To enable: SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS daily_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    CALL CleanOldData();

-- Optimize tables weekly
CREATE EVENT IF NOT EXISTS weekly_optimize
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    OPTIMIZE TABLE messages;
    OPTIMIZE TABLE activity_log;
    OPTIMIZE TABLE notifications;
    OPTIMIZE TABLE user_sessions;
END;

-- Grant appropriate permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON bulkvs_portal.* TO 'bulkvs_user'@'localhost';
-- GRANT EXECUTE ON bulkvs_portal.* TO 'bulkvs_user'@'localhost';

-- Final setup verification queries
SELECT 'Database setup completed successfully!' as status;
SELECT COUNT(*) as user_count FROM users;
SELECT COUNT(*) as phone_count FROM phone_numbers;
SELECT COUNT(*) as permission_count FROM user_phone_permissions;