<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Policy.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Payroll.php';
require_once __DIR__ . '/../app/models/SalaryStructure.php';
require_once __DIR__ . '/../app/core/PayrollReadinessEngine.php';

echo "=== 1. Testing Payroll Readiness Evaluation for 2026-10 ===\n";
$eval = PayrollReadinessEngine::evaluateMonth('2026-10', 1);
echo "Readiness Score: {$eval['readiness_score']}%\n";
echo "Total Checkpoints Evaluated: " . count($eval['checkpoints']) . "\n";
echo "Total Exceptions: {$eval['total_exceptions']}\n";
echo "Blocking Errors: {$eval['blocking_errors']}\n";

echo "\n=== 2. Checkpoints Status ===\n";
foreach ($eval['checkpoints'] as $key => $c) {
    $st = is_array($c) ? $c['status'] : $c->status;
    $rec = is_array($c) ? $c['records'] : $c->records_count;
    $exc = is_array($c) ? ($c['blocking'] + $c['warnings']) : $c->exceptions_count;
    $blk = is_array($c) ? $c['blocking'] : $c->blocking_errors_count;
    echo " - [{$st}] {$key} ({$rec} recs, {$exc} exc, {$blk} blk)\n";
}

echo "\n=== 3. Testing Pre-payroll Adjustments & Template Generation ===\n";
$lopCsv = PayrollReadinessEngine::generateSampleTemplate('lop');
echo "LOP Template Sample Lines: " . substr_count($lopCsv, "\n") . "\n";
$incCsv = PayrollReadinessEngine::generateSampleTemplate('incentives');
echo "Incentives Template Sample Lines: " . substr_count($incCsv, "\n") . "\n";

echo "\n=== 4. Testing Manual Adjustment Insertion ===\n";
$db = Database::getInstance()->getConnection();
$emp = $db->query("SELECT employee_id FROM employees LIMIT 1")->fetch(PDO::FETCH_OBJ);
if ($emp) {
    $adjId = PayrollReadinessEngine::addAdjustment([
        'pay_month'   => '2026-10',
        'employee_id' => $emp->employee_id,
        'category'    => 'incentives',
        'amount'      => 7500.00,
        'days'        => 0,
        'reason'      => 'Q3 High Performer Award',
        'remarks'     => 'HR Authorized',
        'source'      => 'manual_entry'
    ], 1);
    echo "Created adjustment ID: {$adjId}\n";
}

echo "\n=== 5. Testing Exception Resolution ===\n";
$openExc = $db->query("SELECT exception_id, title, description FROM payroll_exceptions WHERE pay_month = '2026-10' AND is_resolved = 0 LIMIT 1")->fetch(PDO::FETCH_OBJ);
if ($openExc) {
    echo "Resolving exception ID: {$openExc->exception_id} ({$openExc->title} - {$openExc->description})\n";
    PayrollReadinessEngine::resolveException($openExc->exception_id, 1, 'Verified and approved by HR Manager');
    $reEval = PayrollReadinessEngine::evaluateMonth('2026-10', 1);
    echo "Re-evaluated readiness score: {$reEval['readiness_score']}%, Blocking: {$reEval['blocking_errors']}\n";
} else {
    echo "No open exceptions to resolve.\n";
}

echo "\n=== 6. Pre-Run & Post-Run Re-evaluation ===\n";
$summary = PayrollReadinessEngine::getCheckpointsSummary('2026-10');
echo "Final Readiness: {$summary['readiness_score']}%\n";
echo "ALL TESTS EXECUTED SUCCESSFULLY.\n";
