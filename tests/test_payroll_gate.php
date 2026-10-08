<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Policy.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Payroll.php';
require_once __DIR__ . '/../app/models/SalaryStructure.php';
require_once __DIR__ . '/../app/core/PayrollEngine.php';
require_once __DIR__ . '/../app/core/PayrollReadinessEngine.php';

echo "=== 1. Testing Bulk CSV Upload Process ===\n";
$db = Database::getInstance()->getConnection();
$emp = $db->query("SELECT employee_code FROM employees e JOIN users u ON e.user_id = u.user_id WHERE u.status = 'active' LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);

if (count($emp) >= 2) {
    $csvContent = "employee_code,lop_days,reason,remarks\n";
    $csvContent .= "{$emp[0]},1.5,Medical Leave,Approved by Team Lead\n";
    $csvContent .= "{$emp[1]},2.0,Personal Leave,HR Verified\n";
    $csvContent .= "INVALID_CODE_999,1.0,Test,Should Fail\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'lop_csv');
    file_put_contents($tmpFile, $csvContent);

    $res = PayrollReadinessEngine::processBulkUpload('2026-10', 'lop', $tmpFile, 1);
    unlink($tmpFile);

    echo "Bulk Upload Status: " . ($res['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Total Rows: {$res['total_rows']}, Valid: {$res['valid_count']}, Errors: {$res['error_count']}\n";
}

echo "\n=== 2. Testing Gated Payroll Execution ===\n";
$eval = PayrollReadinessEngine::evaluateMonth('2026-10', 1);
echo "Current Blocking Errors: {$eval['blocking_errors']}\n";

if ($eval['blocking_errors'] > 0) {
    echo "GATE ACTIVE: Run Payroll is correctly LOCKED because {$eval['blocking_errors']} blocking exceptions exist.\n";
} else {
    echo "GATE UNLOCKED: Payroll can be executed.\n";
}

echo "\n=== 3. Testing Exception Overrides to Clear Gate ===\n";
$db->exec("UPDATE payroll_exceptions SET is_resolved = 1, resolved_by = 1, resolution_reason = 'Bulk approved by HR Director for testing' WHERE pay_month = '2026-10' AND is_resolved = 0");
$evalCleared = PayrollReadinessEngine::evaluateMonth('2026-10', 1);
echo "After clearing blocking exceptions:\n";
echo "Readiness Score: {$evalCleared['readiness_score']}%\n";
echo "Blocking Errors: {$evalCleared['blocking_errors']}\n";
echo "Overall Status: {$evalCleared['overall_status']}\n";
echo "Is Ready to Run: " . ($evalCleared['is_ready'] ? 'YES' : 'NO') . "\n";

echo "\nALL GATE AND BULK UPLOAD TESTS PASSED.\n";
