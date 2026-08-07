<?php
require_once 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>Starting Portal Database Upgrade...</h3>";

    // 1. Roles & Permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created IFW_roles table.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created IFW_permissions table.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_role_permissions (
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES IFW_roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES IFW_permissions(id) ON DELETE CASCADE
    )");
    echo "Created IFW_role_permissions table.<br>";

    // Alter IFW_users role column
    $pdo->exec("ALTER TABLE IFW_users MODIFY role VARCHAR(50) DEFAULT 'agent'");
    echo "Modified IFW_users.role column.<br>";

    // Seed default roles and permissions
    $defaultRoles = ['admin', 'agent', 'attorney', 'staff'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO IFW_roles (name) VALUES (?)");
    foreach ($defaultRoles as $role) {
        $stmt->execute([$role]);
    }

    $defaultPermissions = [
        'manage_cases', 'view_cases', 'manage_invoices', 'view_invoices', 
        'manage_documents', 'view_documents', 'manage_staff', 'manage_clients', 'manage_settings'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO IFW_permissions (name) VALUES (?)");
    foreach ($defaultPermissions as $perm) {
        $stmt->execute([$perm]);
    }
    
    // Give admin all permissions
    $adminRole = $pdo->query("SELECT id FROM IFW_roles WHERE name = 'admin'")->fetchColumn();
    if ($adminRole) {
        $perms = $pdo->query("SELECT id FROM IFW_permissions")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT IGNORE INTO IFW_role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($perms as $permId) {
            $stmt->execute([$adminRole, $permId]);
        }
    }
    echo "Seeded roles and permissions.<br>";

    // 2. Cases
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_cases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_number VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        client_id INT NOT NULL,
        status ENUM('pending', 'active', 'resolved', 'closed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE
    )");
    echo "Created IFW_cases table.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_case_assignments (
        case_id INT NOT NULL,
        user_id INT NOT NULL,
        PRIMARY KEY (case_id, user_id),
        FOREIGN KEY (case_id) REFERENCES IFW_cases(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES IFW_users(id) ON DELETE CASCADE
    )");
    echo "Created IFW_case_assignments table.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_case_milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('pending', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (case_id) REFERENCES IFW_cases(id) ON DELETE CASCADE
    )");
    echo "Created IFW_case_milestones table.<br>";

    // 3. Case Notes (Fixes Chat 500 error)
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_case_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        case_id INT NULL,
        agent_id INT NOT NULL,
        note TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE,
        FOREIGN KEY (case_id) REFERENCES IFW_cases(id) ON DELETE SET NULL,
        FOREIGN KEY (agent_id) REFERENCES IFW_users(id) ON DELETE CASCADE
    )");
    echo "Created IFW_case_notes table.<br>";

    // 4. Documents
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        case_id INT NULL,
        name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by_user_id INT NULL,
        uploaded_by_client_id INT NULL,
        type VARCHAR(50) DEFAULT 'evidence',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE,
        FOREIGN KEY (case_id) REFERENCES IFW_cases(id) ON DELETE SET NULL,
        FOREIGN KEY (uploaded_by_user_id) REFERENCES IFW_users(id) ON DELETE SET NULL,
        FOREIGN KEY (uploaded_by_client_id) REFERENCES IFW_clients(id) ON DELETE SET NULL
    )");
    echo "Created IFW_documents table.<br>";

    // 5. Invoicing & Ledger
    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        case_id INT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES IFW_clients(id) ON DELETE CASCADE,
        FOREIGN KEY (case_id) REFERENCES IFW_cases(id) ON DELETE SET NULL
    )");
    echo "Created IFW_invoices table.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS IFW_ledger_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'offline',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES IFW_invoices(id) ON DELETE CASCADE
    )");
    echo "Created IFW_ledger_entries table.<br>";

    echo "<h3 style='color: green;'>Upgrade completed successfully!</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}




