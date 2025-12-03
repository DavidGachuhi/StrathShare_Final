-- STRATHSHARE DATABASE SCHEMA

DROP DATABASE IF EXISTS strathshare_db;
CREATE DATABASE strathshare_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE strathshare_db;



-- TABLE 1: USERS
-- Stores all platform users (students and admin)
CREATE TABLE users (
    user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    user_email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15),
    profile_picture_url VARCHAR(255),
    bio TEXT,
    account_status ENUM('pending', 'active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
    account_type ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_provider TINYINT(1) NOT NULL DEFAULT 1,
    is_seeker TINYINT(1) NOT NULL DEFAULT 1,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT(11) DEFAULT 0,
    date_registered DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (user_email),
    INDEX idx_account_status (account_status),
    INDEX idx_is_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 2: SKILLS
-- Predefined and custom skills for services/requests
CREATE TABLE skills (
    skill_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    is_custom TINYINT(1) DEFAULT 0,
    created_by INT(11),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 3: SERVICE LISTINGS
-- Services offered by providers
CREATE TABLE service_listings (
    listing_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    provider_id INT(11) NOT NULL,
    skill_id INT(11) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    price_min DECIMAL(10,2),
    price_max DECIMAL(10,2),
    price_range VARCHAR(50),
    availability TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active', 'paused', 'deleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    INDEX idx_provider (provider_id),
    INDEX idx_skill (skill_id),
    INDEX idx_status (status),
    INDEX idx_availability (availability)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 4: REQUESTS
-- Help requests posted by seekers
-- Status flow: open → assigned → in_progress → awaiting_payment → completed
CREATE TABLE requests (
    request_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    seeker_id INT(11) NOT NULL,
    provider_id INT(11),
    skill_id INT(11) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    budget DECIMAL(10,2),
    deadline DATE,
    status ENUM('open', 'assigned', 'in_progress', 'awaiting_payment', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (seeker_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    INDEX idx_seeker (seeker_id),
    INDEX idx_provider (provider_id),
    INDEX idx_skill (skill_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABLE 5: TRANSACTIONS
-- Payment records for completed work
CREATE TABLE transactions (
    transaction_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    request_id INT(11) NOT NULL,
    payer_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'KES',
    payment_method VARCHAR(20) NOT NULL DEFAULT 'mpesa',
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    mpesa_reference VARCHAR(50),
    mpesa_phone_number VARCHAR(15),
    mpesa_checkout_request_id VARCHAR(100),
    mpesa_merchant_request_id VARCHAR(100),
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (payer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_request (request_id),
    INDEX idx_status (status),
    INDEX idx_checkout_request (mpesa_checkout_request_id),
    INDEX idx_payer (payer_id),
    INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 6: REVIEWS
-- Ratings after completed transactions (both directions)
CREATE TABLE reviews (
    review_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT(11) NOT NULL,
    request_id INT(11) NOT NULL,
    reviewer_id INT(11) NOT NULL,
    reviewee_id INT(11) NOT NULL,
    rating INT(11) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    review_type ENUM('seeker_to_provider', 'provider_to_seeker') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (transaction_id, reviewer_id, review_type),
    INDEX idx_transaction (transaction_id),
    INDEX idx_reviewee (reviewee_id),
    INDEX idx_reviewer (reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 7: MESSAGES
-- In-app messaging between users
CREATE TABLE messages (
    message_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    listing_id INT(11) NULL,
    request_id INT(11) NULL,
    message_text TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES service_listings(listing_id) ON DELETE SET NULL,
    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE SET NULL,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABLE 8: NOTIFICATIONS
-- In-app notifications for users
CREATE TABLE notifications (
    notification_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    type ENUM('request_accepted', 'request_completed', 'payment_received', 'payment_sent', 'new_message', 'new_review', 'request_cancelled', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT(11) NULL,
    reference_type ENUM('request', 'transaction', 'message', 'review', 'service') NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- INSERT DEFAULT SKILLS
INSERT INTO skills (skill_name, category, description, is_custom) VALUES
-- Technical Skills
('Python Programming', 'Technical', 'Programming in Python for data science, web development, automation', 0),
('Java Programming', 'Technical', 'Object-oriented programming in Java for enterprise applications', 0),
('JavaScript/Web Dev', 'Technical', 'HTML, CSS, JavaScript, React, Node.js development', 0),
('Database Design', 'Technical', 'MySQL, PostgreSQL, MongoDB database design and optimization', 0),
('Mobile App Development', 'Technical', 'Android and iOS mobile application development', 0),
('Data Analysis', 'Technical', 'Data analysis using Python, R, Excel, and visualization tools', 0),
('Machine Learning', 'Technical', 'ML model development and implementation', 0),
('Cybersecurity', 'Technical', 'Network security, ethical hacking, penetration testing', 0),
('C/C++ Programming', 'Technical', 'Systems programming in C and C++', 0),
('PHP Development', 'Technical', 'Backend web development with PHP', 0),

-- Creative Skills
('Graphic Design', 'Creative', 'Logo design, posters, branding, social media graphics', 0),
('Video Editing', 'Creative', 'Video production, editing, motion graphics', 0),
('Photography', 'Creative', 'Professional photography for events, portraits, products', 0),
('UI/UX Design', 'Creative', 'User interface and experience design for web and mobile', 0),
('Content Writing', 'Creative', 'Blog posts, articles, copywriting, creative writing', 0),
('Animation', 'Creative', '2D/3D animation, motion graphics', 0),
('Music Production', 'Creative', 'Beat making, mixing, audio production', 0),

-- Academic Skills
('Mathematics Tutoring', 'Academic', 'Calculus, algebra, statistics, discrete math tutoring', 0),
('Physics Tutoring', 'Academic', 'Mechanics, thermodynamics, electromagnetism tutoring', 0),
('Chemistry Tutoring', 'Academic', 'Organic, inorganic, physical chemistry help', 0),
('Economics Tutoring', 'Academic', 'Micro and macroeconomics tutoring', 0),
('Statistics Help', 'Academic', 'Statistical analysis, SPSS, R, probability theory', 0),
('Accounting Help', 'Academic', 'Financial and managerial accounting assistance', 0),

-- Business Skills
('Business Plan Writing', 'Business', 'Complete business plan development and consultation', 0),
('Financial Analysis', 'Business', 'Financial modeling, budgeting, investment analysis', 0),
('Marketing Strategy', 'Business', 'Digital marketing, social media strategy, SEO', 0),
('Presentation Design', 'Business', 'PowerPoint, Keynote presentation creation', 0),
('Project Management', 'Business', 'Project planning, coordination, and execution', 0),

-- Other Skills
('Language Tutoring', 'Other', 'English, French, Swahili language tutoring', 0),
('Music Lessons', 'Other', 'Guitar, piano, vocal training', 0),
('Fitness Training', 'Other', 'Personal training and fitness guidance', 0);


-- INSERT ADMIN USER
-- Password: SeanDavid67#
INSERT INTO users (first_name, last_name, user_email, password_hash, account_type, is_admin, account_status, is_provider, is_seeker, phone_number, bio) 
VALUES (
    'Admin', 
    'StrathShare', 
    'admin@strathmore.edu', 
    '$2y$10$9astdm//DA11NfMzdUi7HuZyxQcdoLOpMCsdCGIP76uJa8yryCADW', 
    'admin', 
    1, 
    'active', 
    0, 
    0,
    '254700000000',
    'System Administrator for StrathShare Platform'
);


-- INSERT SAMPLE STUDENTS (for testing/demo)
-- Password for all test users: Test@123
INSERT INTO users (first_name, last_name, user_email, password_hash, phone_number, account_status, is_provider, is_seeker, bio, average_rating, total_reviews) VALUES
('John', 'Kamau', 'john.kamau@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '254712345678', 'active', 1, 1, 'Computer Science major specializing in web development and machine learning. Available for tutoring and project help!', 4.8, 12),
('Grace', 'Wanjiku', 'grace.wanjiku@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '254723456789', 'active', 1, 1, 'Graphic designer with 2 years of experience. I create stunning visuals for brands and individuals.', 4.9, 18),
('Brian', 'Ochieng', 'brian.ochieng@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '254734567890', 'active', 1, 1, 'Math tutor with passion for making complex concepts simple. Specialized in calculus and statistics.', 5.0, 25),
('Faith', 'Muthoni', 'faith.muthoni@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '254745678901', 'active', 1, 1, 'Business student offering help with financial analysis and business plan writing.', 4.7, 8),
('Kevin', 'Njoroge', 'kevin.njoroge@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '254756789012', 'active', 1, 1, 'Video editor and photographer. Let me help bring your creative projects to life!', 4.6, 15);



-- INSERT SAMPLE SERVICE LISTINGS
INSERT INTO service_listings (provider_id, skill_id, title, description, price_min, price_max, price_range, availability, status) VALUES
-- John's Services (user_id 2)
(2, 1, 'Python Programming Tutoring', 'Comprehensive Python tutoring from basics to advanced. Covers data structures, OOP, Django/Flask, and data science libraries. One-on-one sessions tailored to your pace.', 500, 1000, '500-1000 KES/hour', 1, 'active'),
(2, 3, 'Full-Stack Web Development', 'Build modern, responsive websites using React, Node.js, and MongoDB. Perfect for school projects or portfolios.', 5000, 15000, '5000-15000 KES/project', 1, 'active'),

-- Grace's Services (user_id 3)
(3, 11, 'Professional Logo & Brand Design', 'Creative logo designs for businesses and brands. Includes 3 concepts, unlimited revisions, and all file formats.', 2000, 5000, '2000-5000 KES', 1, 'active'),
(3, 14, 'UI/UX Design for Apps', 'User-centered design for websites and mobile apps. Wireframing, prototyping, and mockups in Figma.', 5000, 15000, '5000-15000 KES/project', 1, 'active'),

-- Brian's Services (user_id 4)
(4, 19, 'Mathematics Tutoring - All Levels', 'Expert tutoring in calculus, linear algebra, statistics, and discrete math. 3 years experience.', 600, 1200, '600-1200 KES/hour', 1, 'active'),
(4, 23, 'Statistics & SPSS Help', 'Statistical analysis using SPSS, R, and Excel. Hypothesis testing, regression, ANOVA.', 800, 2000, '800-2000 KES/session', 1, 'active'),

-- Faith's Services (user_id 5)
(5, 25, 'Business Plan Writing', 'Comprehensive business plans with market research, financials, and pitch decks.', 5000, 15000, '5000-15000 KES', 1, 'active'),
(5, 26, 'Financial Modeling & Analysis', 'Excel financial models, DCF valuation, budgeting, and forecasting.', 3000, 10000, '3000-10000 KES/project', 1, 'active'),

-- Kevin's Services (user_id 6)
(6, 13, 'Event Photography', 'Professional photography for events, graduations, and portraits. Edited photos in 48 hours.', 3000, 10000, '3000-10000 KES/event', 1, 'active'),
(6, 12, 'Video Editing for Social Media', 'Engaging video edits for YouTube, Instagram, TikTok. Fast 24-48 hour turnaround!', 1000, 5000, '1000-5000 KES/video', 1, 'active');


-- INSERT SAMPLE REQUESTS
INSERT INTO requests (seeker_id, skill_id, title, description, budget_min, budget_max, budget, deadline, status) VALUES
(4, 1, 'Need Python Help for Data Science Project', 'Looking for help with pandas and matplotlib for data visualization and statistical analysis.', 500, 1000, 800, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'open'),
(6, 3, 'Build Personal Portfolio Website', 'Need a clean, modern portfolio website with 5 pages. Must be responsive. Content ready.', 5000, 10000, 8000, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'open'),
(2, 11, 'Logo Design for Student Startup', 'Starting CampusBites food delivery. Need modern, memorable logo for app and packaging.', 2000, 4000, 3000, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'open'),
(5, 19, 'Calculus 2 Tutoring for Exam', 'Urgent help for Calculus 2 final. Integration techniques, series, polar coordinates.', 1500, 2500, 2000, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'open'),
(3, 28, 'PowerPoint for Business Pitch', 'Professional pitch deck for entrepreneurship class. 15-20 slides with animations.', 1500, 3000, 2000, DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'open');



-- INSERT SAMPLE MESSAGES
INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at) VALUES
(4, 2, 'Hi John! I saw your Python tutoring service. Are you available this weekend?', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 4, 'Hey Brian! Yes, I am available on Saturday afternoon. What topics do you need help with?', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 2, 'Great! I need help with pandas DataFrames and matplotlib visualizations.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 4, 'Perfect, thats my specialty! We can do a 2-hour session. Does 2 PM work?', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 3, 'Hi Grace! Love your design work. Can you help with a logo for my project?', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, 'Thank you Kevin! Id be happy to help. Tell me about your project and the style you want.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 3, 'Its a campus events app called StrathEvents. Looking for something modern and vibrant.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY));


-- INSERT SAMPLE NOTIFICATIONS
INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type, is_read) VALUES
(2, 'new_message', 'New Message', 'You have a new message from Brian Ochieng', 4, 'message', 0),
(3, 'new_message', 'New Message', 'You have a new message from Kevin Njoroge', 7, 'message', 0),
(4, 'system', 'Welcome to StrathShare!', 'Start by posting your first service or browsing available help.', NULL, NULL, 1);


-- STORED PROCEDURE: Update User Average Rating
-- Called after a new review is submitted
DELIMITER //
CREATE PROCEDURE UpdateUserRating(IN user_id_param INT)
BEGIN
    UPDATE users 
    SET average_rating = (
        SELECT COALESCE(AVG(rating), 0) 
        FROM reviews 
        WHERE reviewee_id = user_id_param
    ),
    total_reviews = (
        SELECT COUNT(*) 
        FROM reviews 
        WHERE reviewee_id = user_id_param
    )
    WHERE user_id = user_id_param;
END//
DELIMITER ;

-- STORED PROCEDURE: Get Unread Message Count
-- Returns count of unread messages for a user

DELIMITER //
CREATE PROCEDURE GetUnreadMessageCount(IN user_id_param INT)
BEGIN
    SELECT COUNT(*) as unread_count 
    FROM messages 
    WHERE receiver_id = user_id_param AND is_read = 0;
END//
DELIMITER ;

-- STORED PROCEDURE: Get Unread Notification Count
-- Returns count of unread notifications for a user
DELIMITER //
CREATE PROCEDURE GetUnreadNotificationCount(IN user_id_param INT)
BEGIN
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = user_id_param AND is_read = 0;
END//
DELIMITER ;

-- VIEW: Active Services with Provider Info
CREATE VIEW view_active_services AS
SELECT 
    sl.listing_id, sl.title, sl.description, sl.price_range, sl.price_min, sl.price_max,
    sl.availability, sl.created_at,
    s.skill_id, s.skill_name, s.category,
    u.user_id, u.first_name, u.last_name, u.profile_picture_url,
    u.average_rating, u.total_reviews
FROM service_listings sl
JOIN skills s ON sl.skill_id = s.skill_id
JOIN users u ON sl.provider_id = u.user_id
WHERE sl.status = 'active' AND sl.availability = 1 AND u.account_status = 'active';


-- VIEW: Open Requests with Seeker Info
CREATE VIEW view_open_requests AS
SELECT 
    r.request_id, r.title, r.description, r.budget, r.deadline, r.created_at,
    s.skill_id, s.skill_name, s.category,
    u.user_id as seeker_id, u.first_name, u.last_name, u.profile_picture_url,
    u.average_rating, u.total_reviews
FROM requests r
JOIN skills s ON r.skill_id = s.skill_id
JOIN users u ON r.seeker_id = u.user_id
WHERE r.status = 'open' AND u.account_status = 'active';


