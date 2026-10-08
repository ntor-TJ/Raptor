<?php
/**
 * Raptor CRM — Payroll Readiness Engine & Pre-Processing Validation Migration
 * -----------------------------------------------------------------------------
 * Adds tables for payroll checkpoints, pre-payroll adjustments (LOP, Incentives,
 * Deductions, Bonus, Arrears), exception tracking, and salary revisions.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

// 1. Table: payroll_readiness_checkpoints
if (!$tableExists($db, 'payroll_readiness_checkpoints')) {
    $sql = "CREATE TABLE payroll_readiness_checkpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pay_month VARCHAR(7) NOT NULL,
        checkpoint_key VARCHAR(50) NOT NULL,
        checkpoint_name VARCHAR(100) NOT NULL,
        status ENUM('not_started', 'in_progress', 'completed', 'completed_with_exceptions', 'action_required', 'failed') NOT NULL DEFAULT 'not_started',
        records_count INT NOT NULL DEFAULT 0,
        exceptions_count INT NOT NULL DEFAULT 0,
        blocking_errors_count INT NOT NULL DEFAULT 0,
        warnings_count INT NOT NULL DEFAULT 0,
        last_evaluated_at DATETIME DEFAULT NULL,
        evaluated_by INT DEFAULT NULL,
        summary_text VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_month_checkpoint (pay_month, checkpoint_key),
        INDEX idx_month (pay_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_readiness_checkpoints table created\n";
}

// 2. Table: payroll_adjustments_pre (LOP, Incentives, Deductions, Bonuses, Arrears, Revisions)
if (!$tableExists($db, 'payroll_adjustments_pre')) {
    $sql = "CREATE TABLE payroll_adjustments_pre (
        adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
        pay_month VARCHAR(7) NOT NULL,
        employee_id INT NOT NULL,
        category ENUM('lop', 'incentive', 'deduction', 'bonus', 'arrears', 'salary_revision', 'new_joiner_proration', 'exit_settlement') NOT NULL,
        sub_type VARCHAR(50) DEFAULT 'general',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        effective_date DATE DEFAULT NULL,
        reason VARCHAR(255) NOT NULL,
        remarks TEXT DEFAULT NULL,
        source ENUM('manual_entry', 'bulk_upload', 'attendance_system', 'leave_management', 'hrms', 'previous_payroll', 'system_calculation') NOT NULL DEFAULT 'manual_entry',
        source_reference VARCHAR(100) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected', 'applied') NOT NULL DEFAULT 'approved',
        created_by INT DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_adj_month (pay_month),
        INDEX idx_adj_emp (employee_id),
        INDEX idx_adj_cat (category),
        CONSTRAINT fk_adj_emp FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_adjustments_pre table created\n";
}

// 3. Table: payroll_exceptions (Detected issues and blocking gates)
if (!$tableExists($db, 'payroll_exceptions')) {
    $sql = "CREATE TABLE payroll_exceptions (
        exception_id INT AUTO_INCREMENT PRIMARY KEY,
        pay_month VARCHAR(7) NOT NULL,
        checkpoint_key VARCHAR(50) NOT NULL,
        employee_id INT DEFAULT NULL,
        severity ENUM('blocking', 'warning', 'info') NOT NULL DEFAULT 'blocking',
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        suggested_action VARCHAR(255) DEFAULT NULL,
        is_resolved TINYINT(1) NOT NULL DEFAULT 0,
        resolved_by INT DEFAULT NULL,
        resolution_reason VARCHAR(255) DEFAULT NULL,
        resolved_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_exc_month (pay_month),
        INDEX idx_exc_check (checkpoint_key),
        INDEX idx_exc_sev (severity),
        INDEX idx_exc_res (is_resolved)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + payroll_exceptions table created\n";
}

// 4. Table: salary_revisions (Track historical salary revisions & arrears)
if (!$tableExists($db, 'salary_revisions')) {
    $sql = "CREATE TABLE salary_revisions (
        revision_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        effective_date DATE NOT NULL,
        effective_pay_month VARCHAR(7) NOT NULL,
        previous_ctc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        revised_ctc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        difference_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        requires_arrears TINYINT(1) NOT NULL DEFAULT 0,
        arrears_calculated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        reason VARCHAR(255) NOT NULL,
        source ENUM('manual_entry', 'bulk_upload', 'hrms', 'performance_appraisal') NOT NULL DEFAULT 'manual_entry',
        status ENUM('pending', 'approved', 'rejected', 'applied') NOT NULL DEFAULT 'approved',
        created_by INT DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_rev_emp (employee_id),
        INDEX idx_rev_month (effective_pay_month),
        CONSTRAINT fk_rev_emp FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "    + salary_revisions table created\n";
}

// 5. Add warnings_acknowledged column to payroll_runs if missing
$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$columnExists($db, 'payroll_runs', 'warnings_acknowledged')) {
    $db->exec("ALTER TABLE payroll_runs ADD COLUMN warnings_acknowledged TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    echo "    + warnings_acknowledged column added to payroll_runs\n";
}
