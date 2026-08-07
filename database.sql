CREATE TABLE IFW_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'agent') DEFAULT 'admin',
    password_hash VARCHAR(255) NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IFW_site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IFW_testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    testimonial_text TEXT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IFW_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IFW_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    password_hash VARCHAR(255) NULL,
    pin_hash VARCHAR(255) NULL,
    assigned_agent_id INT NULL,
    phone VARCHAR(50),
    status ENUM('Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery') DEFAULT 'Received',
    private_notes TEXT NULL,
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_agent_id) REFERENCES IFW_users(id) ON DELETE SET NULL
);

CREATE TABLE IFW_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    sender ENUM('admin', 'client') NOT NULL,
    message_text TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
);

CREATE TABLE IFW_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    document_type ENUM('Standard', 'Service Agreement', 'Power of Attorney', 'NDA', 'Invoice') DEFAULT 'Standard',
    requires_signature BOOLEAN DEFAULT FALSE,
    is_signed BOOLEAN DEFAULT FALSE,
    signed_at TIMESTAMP NULL,
    signature_ip VARCHAR(50) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
);

CREATE TABLE IFW_kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    document_type ENUM('Government ID', 'Proof of Address') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_feedback TEXT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
);

CREATE TABLE IFW_case_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    agent_id INT NOT NULL,
    note_text TEXT NOT NULL,
    is_visible_to_client BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES IFW_users(id) ON DELETE CASCADE
);

CREATE TABLE IFW_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
);

CREATE TABLE IFW_form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'email', 'textarea', 'select', 'checkbox') NOT NULL,
    field_options TEXT, -- JSON for select options
    is_required BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0
);

CREATE TABLE IFW_contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IFW_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES IFW_users(id) ON DELETE SET NULL
);

CREATE TABLE IFW_chat_status (
    user_type ENUM('client', 'admin') NOT NULL,
    user_id INT NOT NULL,
    is_typing BOOLEAN DEFAULT FALSE,
    is_online BOOLEAN DEFAULT FALSE,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_type, user_id)
);

CREATE TABLE IFW_form_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

-- Default Form Settings
INSERT INTO IFW_form_settings (setting_key, setting_value) VALUES 
('recipient_email', 'admin@ifwglobal.com'),
('success_message', 'Thank you for your message. We will get back to you shortly.');

-- Default Form Fields
INSERT INTO IFW_form_fields (field_name, field_label, field_type, is_required, display_order) VALUES
('first_name', 'First Name', 'text', TRUE, 1),
('last_name', 'Last Name', 'text', TRUE, 2),
('email', 'Email Address', 'email', TRUE, 3),
('phone', 'Phone Number', 'text', FALSE, 4),
('message', 'Message', 'textarea', TRUE, 5);

-- Default Admin User (admin / admin@example.com / Password123! / 1234)
INSERT INTO IFW_users (username, email, password_hash, pin_hash) VALUES 
('admin', 'admin@example.com', '$2y$10$ohEO9ShUK15XG/a69ZXXI.8ewNdkhr2GXgnRwkJhrM.qnI5yrwgXO', '$2y$10$QafXQGeBpjj0Do3KZGHsy.VcUcGe13foakJon/tTWXTzawVh./rPG');
