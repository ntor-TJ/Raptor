<?php
/**
 * Raptor CRM — Payroll Module Migration
 * -----------------------------------------------------------------------------
 * Adds salary structures, monthly payroll runs, detailed itemized payslips,
 * statutory benefits/deductions (PF, ESI, Gratuity, PT, TDS), and hourly/trainer logs.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$roleId = function (PDO $db, string $role): ?int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :role LIMIT 1');
    $stmt->execute([':role' => $role]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
};

$permissionId = function (PDO $db, string $permission): ?int {
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :permission LIMIT 1');
    $stmt->execute([':permission' => $permission]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
};

$ensurePermission = function (PDO $db, string $permission, string $description) use ($permissionId): int {
    $id = $permissionId($db, $permission);
    if ($id) {
        return $id;
    }
    $stmt = $db->prepare('INSERT INTO permissions (permission_name, description) VALUES (:p, :d)');
    $stmt->execute([':p' => $permission, ':d' => $description]);
    return (int) $db->lastInsertId();
};

$assignPermission = function (PDO $db, int $roleId, int $permissionId): void {
    $stmt = $db->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = :r AND permission_id = :p');
    $stmt->execute([':r' => $roleId, ':p' => $permissionId]);
    if ((int) $stmt->fetchColumn() === 0) {
        $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)');
        $stmt->execute([':r' => $roleId, ':p' => $permissionId]);
    }
};

// 1. Ensure Payroll Permissions
$permManagePayroll = $ensurePermission($db, 'manage_payroll', 'Create, calculate, and manage employee salary structures and payroll runs.');
$permApprovePayroll = $ensurePermission($db, 'approve_payroll', 'Approve and finalize monthly payroll disbursements.');
$permViewOwnPayroll = $ensurePermission($db, 'view_own_payroll', 'View and download own payslips.');

$adminRoleId = $roleId($db, 'admin');
$hrRoleId = $roleId($db, 'hr');
$managerRoleId = $roleId($db, 'manager');
$employeeRoleId = $roleId($db, 'employee');
$analystRoleId = $roleId($db, 'analyst');

if ($adminRoleId) {
    $assignPermission($db, $adminRoleId, $permManagePayroll);
    $assignPermission($db, $adminRoleId, $permApprovePayroll);
    $assignPermission($db, $adminRoleId, $permViewOwnPayroll);
}
if ($hrRoleId) {
    $assignPermission($db, $hrRoleId, $permManagePayroll);
    $assignPermission($db, $hrRoleId, $permViewOwnPayroll);
}
if ($managerRoleId) {
    $assignPermission($db, $managerRoleId, $permViewOwnPayroll);
}
if ($employeeRoleId) {
    $assignPermission($db, $employeeRoleId, $permViewOwnPayroll);
}
if ($analystRoleId) {
    $assignPermission($db, $analystRoleId, $permViewOwnPayroll);
}
echo "    + payroll permissions registered and assigned\n";

// 2. Table: salary_structures
if (!$tableExists($db, 'salary_structures')) {
    $sql = "CREATE TABLE salary_structures (
        structure_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL UNIQUE,
        employment_type ENUM('full_time_pf', 'full_time_no_pf', 'consultant', 'trainer_hourly', 'bench') DEFAULT 'full_time_pf',
        monthly_ctc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        hra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        special_allowance DECIMAL(12,2) NOT NULL DEFAULT 1600.00,
        other_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        pf_applicable TINYINT(1) NOT NULL DEFAULT 1,
        esi_applicable TINYINT(1) NOT NULL DEFAULT 0,
        pt_applicable TINYINT(1) NOT NULL DEFAULT 1,
        hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        bank_account_number VARCHAR(50) DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        bank_ifsc VARCHAR(20) DEFAULT NULL,
        account_holder_name VARCHAR(150) DEFAULT NULL,
        pan_number VARCHAR(20) DEFAULT NULL,
        uan_number VARCHAR(30) DEFAULT NULL,
        pf_number VARCHAR(50) DEFAULT NULL,
        effective_from DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_salary_emp FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + salary_structures table created\n";
}

// 3. Table: payroll_runs
if (!$tableExists($db, 'payroll_runs')) {
    $sql = "CREATE TABLE payroll_runs (
        payroll_run_id INT AUTO_INCREMENT PRIMARY KEY,
        pay_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format e.g. 2026-08',
        title VARCHAR(100) NOT NULL,
        calendar_days INT NOT NULL DEFAULT 30,
        pay_date DATE NOT NULL,
        status ENUM('draft', 'submitted', 'approved', 'disbursed', 'cancelled') DEFAULT 'draft',
        total_employees INT NOT NULL DEFAULT 0,
        total_gross_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        total_net_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        total_employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        created_by INT NOT NULL,
        submitted_at DATETIME DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        approval_remarks TEXT DEFAULT NULL,
        disbursed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_payroll_month (pay_month),
        CONSTRAINT fk_payroll_created_by FOREIGN KEY (created_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_runs table created\n";
}

// 4. Table: payroll_items (Individual Payslips)
if (!$tableExists($db, 'payroll_items')) {
    $sql = "CREATE TABLE payroll_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT NOT NULL,
        employee_id INT NOT NULL,
        employment_type VARCHAR(30) NOT NULL DEFAULT 'full_time_pf',
        calendar_days INT NOT NULL DEFAULT 30,
        paid_days DECIMAL(5,2) NOT NULL DEFAULT 30.00,
        lop_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        monthly_ctc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        basic DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        hra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        other_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        gross_income DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        arrears DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        reimbursements DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        bonus_incentive DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        gross_earnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        pf_employee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        esi_employee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        professional_tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tds DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        lop_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        pf_employer DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        esi_employer DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        gratuity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_employer_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        net_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        net_salary_in_words VARCHAR(255) DEFAULT NULL,
        bank_account_number VARCHAR(50) DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        bank_ifsc VARCHAR(20) DEFAULT NULL,
        account_holder_name VARCHAR(150) DEFAULT NULL,
        pan_number VARCHAR(20) DEFAULT NULL,
        uan_number VARCHAR(30) DEFAULT NULL,
        pf_number VARCHAR(50) DEFAULT NULL,
        payment_status ENUM('pending', 'hold', 'processed', 'paid') DEFAULT 'pending',
        payment_remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_run_emp (payroll_run_id, employee_id),
        KEY idx_payroll_emp (employee_id),
        KEY idx_payroll_status (payment_status),
        CONSTRAINT fk_payitem_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(payroll_run_id) ON DELETE CASCADE,
        CONSTRAINT fk_payitem_emp FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_items table created\n";
}

// 5. Table: payroll_hourly_logs
if (!$tableExists($db, 'payroll_hourly_logs')) {
    $sql = "CREATE TABLE payroll_hourly_logs (
        hourly_log_id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT NOT NULL,
        trainer_name VARCHAR(150) NOT NULL,
        unit_type ENUM('hours', 'days') DEFAULT 'hours',
        units DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        rate_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        bank_account_number VARCHAR(50) DEFAULT NULL,
        bank_ifsc VARCHAR(20) DEFAULT NULL,
        account_holder_name VARCHAR(150) DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        payment_status ENUM('pending', 'hold', 'paid') DEFAULT 'pending',
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_hourly_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(payroll_run_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_hourly_logs table created\n";
}
