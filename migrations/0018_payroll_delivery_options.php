<?php
/**
 * Raptor CRM — Payroll Delivery & Eligibility Migration
 * -----------------------------------------------------------------------------
 * Adds is_eligible_payslip and email_sent_at columns to payroll_items
 * for selective payslip generation/distribution and email tracking.
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$columnExists($db, 'payroll_items', 'is_eligible_payslip')) {
    $db->exec("ALTER TABLE payroll_items ADD COLUMN is_eligible_payslip TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_remarks");
    echo "    + is_eligible_payslip column added to payroll_items\n";
}

if (!$columnExists($db, 'payroll_items', 'email_sent_at')) {
    $db->exec("ALTER TABLE payroll_items ADD COLUMN email_sent_at DATETIME DEFAULT NULL AFTER is_eligible_payslip");
    echo "    + email_sent_at column added to payroll_items\n";
}
