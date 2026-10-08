<?php
/**
 * Raptor CRM — Devil's Advocate Adversarial Stress & Security Test Suite
 * =============================================================================
 * Attacks payroll calculation logic, edge cases, statutory limits, boundary
 * values, unauthorized access vectors, gating bypasses, data manipulation,
 * and downstream module side-effects.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Policy.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/PayrollEngine.php';
require_once __DIR__ . '/../app/core/PayrollReadinessEngine.php';
require_once __DIR__ . '/../app/models/Payroll.php';
require_once __DIR__ . '/../app/models/SalaryStructure.php';

$db = Database::getInstance()->getConnection();
$payrollModel = new Payroll();
$salaryModel = new SalaryStructure();

$passed = 0;
$failed = 0;
$testNum = 0;

function assertTest(string $description, bool $condition, string $details = '') {
    global $passed, $failed, $testNum;
    $testNum++;
    if ($condition) {
        $passed++;
        echo "  [PASS] Test #{$testNum}: {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] Test #{$testNum}: {$description}\n";
        if ($details) echo "         Details: {$details}\n";
    }
}

echo "========================================================================\n";
echo "  RAPTOR CRM — DEVIL'S ADVOCATE ADVERSARIAL PAYROLL & SECURITY SUITE\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// VECTOR 1: Mathematical Calculation & Boundary Limits
// -----------------------------------------------------------------------------
echo ">>> VECTOR 1: Math Engine & Boundary Limit Edge-Cases\n";

// Test 1.1: Standard Breakup Split for ₹50,000 CTC
$breakup = PayrollEngine::breakdownCTC(50000.00, 'full_time_pf', true, false);
$expectedBasic = round(50000.00 * 0.50, 2); // ₹25,000
$expectedHra = round($expectedBasic * 0.50, 2); // ₹12,500
assertTest("CTC ₹50,000 gives 50% Basic (₹25,000)", $breakup['base_salary'] == $expectedBasic, "Got: {$breakup['base_salary']}");
assertTest("CTC ₹50,000 gives 50% of Basic as HRA (₹12,500)", $breakup['hra'] == $expectedHra, "Got: {$breakup['hra']}");

// Test 1.2: Statutory Caps: PF Employee Cap @ ₹1,800
$structure = [
    'monthly_ctc'           => 100000.00,
    'base_salary'           => 50000.00,
    'hra'                   => 25000.00,
    'special_allowance'     => 15000.00,
    'other_allowance'       => 10000.00,
    'pf_applicable'         => 1,
    'esi_applicable'        => 0,
    'professional_tax'      => 200.00,
    'tds_monthly'           => 5000.00,
    'employer_pf'           => 1800.00,
    'employer_esi'          => 0.00,
    'employer_gratuity'     => 2405.00
];
$slip = PayrollEngine::calculatePayslipItem($structure, 30, 30, 0, []);
assertTest("PF Employee contribution capped strictly at ₹1,800 for high earners", $slip['pf_employee'] == 1800.00, "Got: {$slip['pf_employee']}");
assertTest("PF Employer contribution matches at ₹1,800", $slip['pf_employer'] == 1800.00, "Got: {$slip['pf_employer']}");

// Test 1.3: ESI wage ceiling rule (ESI should only apply if Gross <= 21,000)
$lowEarnStructure = array_merge($structure, [
    'monthly_ctc'     => 18000.00,
    'base_salary'     => 9000.00,
    'hra'             => 4500.00,
    'special_allowance'=> 4500.00,
    'other_allowance' => 0.00,
    'esi_applicable'  => 1
]);
$lowSlip = PayrollEngine::calculatePayslipItem($lowEarnStructure, 30, 30, 0, []);
$expectedEsi = round(18000.00 * 0.0075, 2); // 135
assertTest("ESI applies (0.75%) when gross is within statutory ceiling <= ₹21,000", $lowSlip['esi_employee'] == $expectedEsi, "Got: {$lowSlip['esi_employee']}");

// Test 1.4: Pro-Rata salary calculation for 15 days out of 30
$halfSlip = PayrollEngine::calculatePayslipItem($structure, 30, 15, 15, []);
$expectedHalfGross = round(100000.00 * (15 / 30), 2);
assertTest("15-day LOP exactly halves gross earnings (₹50,000)", $halfSlip['gross_earnings'] == $expectedHalfGross, "Got: {$halfSlip['gross_earnings']}");

// Test 1.5: Zero paid days / 100% LOP gives ₹0 Gross & Net
$zeroSlip = PayrollEngine::calculatePayslipItem($structure, 30, 0, 30, []);
assertTest("100% LOP results in exactly ₹0.00 Gross Salary", $zeroSlip['gross_earnings'] == 0.00, "Got: {$zeroSlip['gross_earnings']}");
assertTest("100% LOP results in ₹0.00 Net Salary", $zeroSlip['net_salary'] == 0.00, "Got: {$zeroSlip['net_salary']}");

// Test 1.6: Excess Deductions handling -> Net Salary floored at ₹0.00
$deductSlip = PayrollEngine::calculatePayslipItem($structure, 30, 30, 0, [
    'other_deductions' => 150000.00 // Exceeds gross of 100,000
]);
assertTest("Excess deductions floor net salary safely at ₹0.00 without negative payout leak", $deductSlip['net_salary'] == 0.00 && $deductSlip['total_deductions'] > $deductSlip['gross_earnings'], "Got: {$deductSlip['net_salary']}");

echo "\n>>> VECTOR 2: Pre-Payroll Readiness Gating & Security Control\n";

// Test 2.1: 14 Checkpoints are initialized and evaluated
$testMonth = '2026-11';
$eval = PayrollReadinessEngine::evaluateMonth($testMonth, 1);
assertTest("Engine evaluates exactly 14 mandatory checkpoints", count($eval['checkpoints']) === 14, "Got: " . count($eval['checkpoints']));

// Test 2.2: LOP overflow validation (e.g. 35 days in November 30 days)
$emp = $db->query("SELECT employee_id, employee_code FROM employees LIMIT 1")->fetch(PDO::FETCH_OBJ);
PayrollReadinessEngine::addAdjustment([
    'pay_month'   => $testMonth,
    'employee_id' => $emp->employee_id,
    'category'    => 'lop',
    'days'        => 35.0, // Invalid!
    'reason'      => 'Adversarial LOP Overflow Test',
    'source'      => 'manual_entry'
], 1);
$evalLop = PayrollReadinessEngine::evaluateMonth($testMonth, 1);
$lopCp = $evalLop['checkpoints']['lop'] ?? [];
$lopBlk = is_array($lopCp) ? $lopCp['blocking'] : $lopCp->blocking_errors_count;
assertTest("LOP exceeding calendar days triggers BLOCKING exception", $lopBlk > 0, "Blocking count: {$lopBlk}");

// Test 2.3: Gated Execution Prevention (Must refuse to run when blocking errors > 0)
assertTest("Payroll run gate is strictly LOCKED when blocking errors exist", $evalLop['is_ready'] === false, "is_ready: " . ($evalLop['is_ready'] ? 'true' : 'false'));

// Test 2.4: Negative Incentive / Deduction Prevention
PayrollReadinessEngine::addAdjustment([
    'pay_month'   => $testMonth,
    'employee_id' => $emp->employee_id,
    'category'    => 'incentives',
    'amount'      => -5000.00, // Invalid negative amount
    'reason'      => 'Adversarial Negative Incentive Test',
    'source'      => 'manual_entry'
], 1);
$evalInc = PayrollReadinessEngine::evaluateMonth($testMonth, 1);
$incCp = $evalInc['checkpoints']['incentives'] ?? [];
$incBlk = is_array($incCp) ? $incCp['blocking'] : $incCp->blocking_errors_count;
assertTest("Negative variable amounts trigger BLOCKING exception", $incBlk > 0, "Blocking count: {$incBlk}");

echo "\n>>> VECTOR 3: CSV Bulk Upload Attack & Malformed Inputs\n";

// Test 3.1: Malformed CSV with non-existent employee codes, SQL injection strings, and invalid numbers
$badCsv = "employee_code,lop_days,reason,remarks\n";
$badCsv .= "NON_EXISTENT_9999,5,' OR 1=1 --,Injection test\n";
$badCsv .= "{$emp->employee_code},-10,Negative days,Should fail\n";
$badCsv .= "{$emp->employee_code},40,Overflow days,Should fail\n";
$badCsv .= "{$emp->employee_code},abc,Invalid type,Should fail\n";

$tmpPath = tempnam(sys_get_temp_dir(), 'bad_csv_');
file_put_contents($tmpPath, $badCsv);
$uploadRes = PayrollReadinessEngine::processBulkUpload($testMonth, 'lop', $tmpPath, 1);
unlink($tmpPath);

assertTest("Bulk CSV rejects invalid/malicious rows without crashes", $uploadRes['error_count'] >= 4, "Errors caught: {$uploadRes['error_count']}");
assertTest("Bulk CSV imports 0 valid rows from corrupted input", $uploadRes['valid_count'] === 0, "Valid count: {$uploadRes['valid_count']}");

echo "\n>>> VECTOR 4: Salary Revisions & Retroactive Arrear Cascading\n";

// Test 4.1: Record salary revision with arrears
$stmtRev = $db->prepare("INSERT INTO salary_revisions 
    (employee_id, effective_date, effective_pay_month, previous_ctc, revised_ctc, difference_amount, requires_arrears, reason, source, status, approved_by, approved_at)
    VALUES (:e, '2026-10-01', :m, 50000.00, 60000.00, 10000.00, 1, 'Promotion Hike', 'manual_entry', 'approved', 1, NOW())");
$stmtRev->execute([':e' => $emp->employee_id, ':m' => $testMonth]);
$revId = (int)$db->lastInsertId();
assertTest("Salary revision record inserted with retro-arrear flag", $revId > 0, "Revision ID: {$revId}");

// Clear test adjustments for clean run
$db->exec("DELETE FROM payroll_adjustments_pre WHERE pay_month = '{$testMonth}'");
$db->exec("DELETE FROM payroll_exceptions WHERE pay_month = '{$testMonth}'");
$db->exec("DELETE FROM salary_revisions WHERE revision_id = {$revId}");

echo "\n>>> VECTOR 5: Access Control & Authorization Boundaries\n";

// Test 5.1: Policy checks
$_SESSION['user_role'] = 'employee';
assertTest("Employee role CANNOT manage employees", Policy::canManageEmployees() === false);
assertTest("Employee role is identified as isEmployee()", Policy::isEmployee() === true);
assertTest("Employee role CANNOT approve data edits", Policy::canApproveDataEdit() === false);

$_SESSION['user_role'] = 'hr';
assertTest("HR role CAN manage employees & pre-payroll", Policy::canManageEmployees() === true);
assertTest("HR role is identified as isHr()", Policy::isHr() === true);

$_SESSION['user_role'] = 'admin';
assertTest("Admin role has master access", Policy::isAdmin() === true);

echo "\n>>> VECTOR 6: Downstream Data Integrity & Clean Dispatches\n";

// Test 6.1: Run generation and Payslip Eligibility
$run = $db->query("SELECT * FROM payroll_runs ORDER BY payroll_run_id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);
if ($run) {
    $items = $payrollModel->getPayrollItems((int)$run->payroll_run_id);
    assertTest("Payroll run {$run->title} has generated item records", count($items) > 0, "Count: " . count($items));
    
    // Test eligibility toggle
    $firstItem = $items[0];
    $initialEligible = (int)$firstItem->is_eligible_payslip;
    $payrollModel->toggleItemEligibility((int)$firstItem->item_id);
    $updatedItem = $payrollModel->getItemById((int)$firstItem->item_id);
    assertTest("Toggling employee payslip eligibility updates state", (int)$updatedItem->is_eligible_payslip !== $initialEligible);
    
    // Restore
    $payrollModel->toggleItemEligibility((int)$firstItem->item_id);
}

echo "\n========================================================================\n";
echo "  DEVIL'S ADVOCATE TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================================\n";
