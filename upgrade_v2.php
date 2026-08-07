<?php
require_once 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Database Synchronization...\n<br><br>";

    // Function to check if a column exists
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Function to check if a table exists
    function tableExists($pdo, $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return $stmt->fetchColumn() > 0;
    }

    // --- PHASE 1 REPAIRS (Missing from Live Server) ---
    if (!columnExists($pdo, 'IFW_users', 'role')) {
        $pdo->exec("ALTER TABLE IFW_users ADD COLUMN role ENUM('admin', 'agent') DEFAULT 'admin' AFTER email");
        echo "Added 'role' column to IFW_users.<br>\n";
    }
    
    if (!columnExists($pdo, 'IFW_clients', 'password_hash')) {
        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        echo "Added 'password_hash' column to IFW_clients.<br>\n";
    }

    if (!columnExists($pdo, 'IFW_clients', 'pin_hash')) {
        if (columnExists($pdo, 'IFW_clients', 'password_hash')) {
            $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN pin_hash VARCHAR(255) NULL AFTER password_hash");
        } else {
            $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN pin_hash VARCHAR(255) NULL");
        }
        echo "Added 'pin_hash' column to IFW_clients.<br>\n";
    }

    if (!columnExists($pdo, 'IFW_clients', 'assigned_agent_id')) {
        if (columnExists($pdo, 'IFW_clients', 'pin_hash')) {
            $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN assigned_agent_id INT NULL AFTER pin_hash");
        } else {
            $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN assigned_agent_id INT NULL");
        }
        $pdo->exec("ALTER TABLE IFW_clients ADD FOREIGN KEY (assigned_agent_id) REFERENCES IFW_users(id) ON DELETE SET NULL");
        echo "Added 'assigned_agent_id' column to IFW_clients.<br>\n";
    }

    if (!columnExists($pdo, 'IFW_clients', 'status')) {
        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN status ENUM('Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery') DEFAULT 'Received'");
        echo "Added 'status' column to IFW_clients.<br>\n";
    }

    if (!columnExists($pdo, 'IFW_clients', 'private_notes')) {
        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN private_notes TEXT NULL");
        echo "Added 'private_notes' column to IFW_clients.<br>\n";
    }

    if (!columnExists($pdo, 'IFW_clients', 'last_seen')) {
        $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN last_seen TIMESTAMP NULL");
        echo "Added 'last_seen' column to IFW_clients.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_testimonials')) {
        $pdo->exec("CREATE TABLE IFW_testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(100) NOT NULL,
            location VARCHAR(100),
            testimonial_text TEXT NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Created IFW_testimonials table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_contact_submissions')) {
        $pdo->exec("CREATE TABLE IFW_contact_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Created IFW_contact_submissions table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_audit_logs')) {
        $pdo->exec("CREATE TABLE IFW_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES IFW_users(id) ON DELETE SET NULL
        )");
        echo "Created IFW_audit_logs table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_chat_status')) {
        $pdo->exec("CREATE TABLE IFW_chat_status (
            user_type ENUM('client', 'admin') NOT NULL,
            user_id INT NOT NULL,
            is_typing BOOLEAN DEFAULT FALSE,
            is_online BOOLEAN DEFAULT FALSE,
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_type, user_id)
        )");
        echo "Created IFW_chat_status table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_site_settings')) {
        $pdo->exec("CREATE TABLE IFW_site_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "Created IFW_site_settings table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_faqs')) {
        $pdo->exec("CREATE TABLE IFW_faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Created IFW_faqs table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_form_settings')) {
        $pdo->exec("CREATE TABLE IFW_form_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL
        )");
        $pdo->exec("INSERT IGNORE INTO IFW_form_settings (setting_key, setting_value) VALUES 
            ('recipient_email', 'admin@ifwglobal.com'),
            ('success_message', 'Thank you for your message. We will get back to you shortly.')");
        echo "Created IFW_form_settings table.<br>\n";
    }

    if (!tableExists($pdo, 'IFW_form_fields')) {
        $pdo->exec("CREATE TABLE IFW_form_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            field_name VARCHAR(100) NOT NULL,
            field_label VARCHAR(100) NOT NULL,
            field_type ENUM('text', 'email', 'textarea', 'select', 'checkbox') NOT NULL,
            field_options TEXT,
            is_required BOOLEAN DEFAULT FALSE,
            display_order INT DEFAULT 0
        )");
        $pdo->exec("INSERT IGNORE INTO IFW_form_fields (field_name, field_label, field_type, is_required, display_order) VALUES
            ('first_name', 'First Name', 'text', TRUE, 1),
            ('last_name', 'Last Name', 'text', TRUE, 2),
            ('email', 'Email Address', 'email', TRUE, 3),
            ('phone', 'Phone Number', 'text', FALSE, 4),
            ('message', 'Message', 'textarea', TRUE, 5)");
        echo "Created IFW_form_fields table.<br>\n";
    }

    // --- PHASE 2 UPGRADES (Current) ---
    
    // 1. Create IFW_kyc_documents
    if (!tableExists($pdo, 'IFW_kyc_documents')) {
        $pdo->exec("
            CREATE TABLE IFW_kyc_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                document_type ENUM('Government ID', 'Proof of Address') NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                admin_feedback TEXT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
            )
        ");
        echo "Created IFW_kyc_documents table.<br>\n";
    }

    // 2. Modify IFW_documents
    if (!columnExists($pdo, 'IFW_documents', 'document_type')) {
        $pdo->exec("ALTER TABLE IFW_documents ADD COLUMN document_type ENUM('Standard', 'Service Agreement', 'Power of Attorney', 'NDA', 'Invoice') DEFAULT 'Standard'");
        $pdo->exec("ALTER TABLE IFW_documents ADD COLUMN requires_signature BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE IFW_documents ADD COLUMN is_signed BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE IFW_documents ADD COLUMN signed_at TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE IFW_documents ADD COLUMN signature_ip VARCHAR(50) NULL");
        echo "Modified IFW_documents table for E-Signatures.<br>\n";
    }

    // 3. Create IFW_case_notes
    if (!tableExists($pdo, 'IFW_case_notes')) {
        $pdo->exec("
            CREATE TABLE IFW_case_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                agent_id INT NOT NULL,
                note_text TEXT NOT NULL,
                is_visible_to_client BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE,
                FOREIGN KEY (agent_id) REFERENCES IFW_users(id) ON DELETE CASCADE
            )
        ");
        echo "Created IFW_case_notes table.<br>\n";
        
        // Migrate existing private notes
        if (columnExists($pdo, 'IFW_clients', 'private_notes')) {
            $stmt = $pdo->query("SELECT id, assigned_agent_id, private_notes FROM IFW_clients WHERE private_notes IS NOT NULL AND private_notes != ''");
            $clients = $stmt->fetchAll();
            $insertNote = $pdo->prepare("INSERT INTO IFW_case_notes (client_id, agent_id, note_text) VALUES (?, ?, ?)");
            foreach ($clients as $c) {
                $agent_id = $c['assigned_agent_id'] ?: 1; // Fallback to admin (ID 1)
                $insertNote->execute([$c['id'], $agent_id, $c['private_notes']]);
            }
            echo "Migrated existing private notes to new case notes feed.<br>\n";
        }
    }

    // 4. Create IFW_invoices
    if (!tableExists($pdo, 'IFW_invoices')) {
        $pdo->exec("
            CREATE TABLE IFW_invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
            )
        ");
        echo "Created IFW_invoices table.<br>\n";
    }

    echo "<br><b>Database Synchronization Completed Successfully!</b>\n";

} catch (PDOException $e) {
    echo "<br><b style='color:red;'>Database Upgrade Error:</b> " . htmlspecialchars($e->getMessage()) . "\n";
}
?>




