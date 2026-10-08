<?php
/**
 * Raptor CRM — Historical & Sample Payroll Data Seeder
 * -----------------------------------------------------------------------------
 * Loads real sample data from payroll_sample_inputs (April 2026 to September 2026),
 * sets up exact salary structures, itemized payslips, statutory deductions,
 * LOP adjustments, and trainer payout logs.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/PayrollEngine.php';
require_once __DIR__ . '/../app/models/SalaryStructure.php';
require_once __DIR__ . '/../app/models/Payroll.php';

echo "=== Raptor CRM Sample Payroll Data Seeder ===\n";

$jsonPath = dirname(__DIR__) . '/payroll_sample_inputs/extracted_payroll_data.json';
if (!file_exists($jsonPath)) {
    die("Error: extracted_payroll_data.json not found. Run python bin/extract_sample_data.py first.\n");
}

$raw = json_decode(file_get_contents($jsonPath), true);
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Get role IDs
$stmt = $db->query("SELECT role_id, role_name FROM roles");
$roles = [];
foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
    $roles[$r->role_name] = (int) $r->role_id;
}
$employeeRoleId = $roles['employee'] ?? 5;
$adminRoleId = $roles['admin'] ?? 1;

// 2. Helper to find or create employee
function getOrCreateEmployee(PDO $db, string $empCode, string $name, string $dept = 'Development', string $designation = 'Software Engineer', string $doj = '2026-01-01', int $roleId = 5): int {
    // Check if employee with code exists
    $stmt = $db->prepare("SELECT employee_id FROM employees WHERE employee_code = :code LIMIT 1");
    $stmt->execute([':code' => $empCode]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    // Check by user name
    $stmt = $db->prepare("SELECT e.employee_id FROM employees e JOIN users u ON e.user_id = u.user_id WHERE u.name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $stmt = $db->prepare("UPDATE employees SET employee_code = :code WHERE employee_id = :id AND (employee_code IS NULL OR employee_code = '')");
        $stmt->execute([':code' => $empCode, ':id' => $id]);
        return (int) $id;
    }

    // Create user
    $emailPrefix = preg_replace('/[^a-z0-9]/', '', strtolower(str_replace(' ', '', $name)));
    if (empty($emailPrefix)) $emailPrefix = 'emp' . rand(100, 999);
    $email = $emailPrefix . '@raptor.test';

    // Ensure unique email
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ((int)$stmt->fetchColumn() > 0) {
        $email = $emailPrefix . rand(10, 99) . '@raptor.test';
    }

    $passwordHash = password_hash('Raptor@12345', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (:role, :name, :email, :pass, 'active')");
    $stmt->execute([
        ':role'  => $roleId,
        ':name'  => $name,
        ':email' => $email,
        ':pass'  => $passwordHash
    ]);
    $userId = (int) $db->lastInsertId();

    $parsedDoj = '2026-01-01';
    if (!empty($doj)) {
        $ts = strtotime($doj);
        if ($ts !== false && $ts > 0) {
            $parsedDoj = date('Y-m-d', $ts);
        }
    }

    // Create employee record
    $stmt = $db->prepare("INSERT INTO employees (user_id, employee_code, department, job_title, hire_date, status)
                          VALUES (:uid, :code, :dept, :title, :doj, 'active')");
    $stmt->execute([
        ':uid'   => $userId,
        ':code'  => $empCode,
        ':dept'  => $dept,
        ':title' => $designation,
        ':doj'   => $parsedDoj
    ]);
    return (int) $db->lastInsertId();
}

$salaryStructureModel = new SalaryStructure();
$payrollModel = new Payroll();

// Map of bank IFSC to names
$bankMap = [
    'FDRL' => 'Federal Bank',
    'HDFC' => 'HDFC Bank',
    'SBIN' => 'State Bank of India',
    'ICIC' => 'ICICI Bank',
    'UBIN' => 'Union Bank of India',
    'PUNB' => 'Punjab National Bank',
    'CNRB' => 'Canara Bank',
    'IOBA' => 'Indian Overseas Bank',
    'BARB' => 'Bank of Baroda',
    'IDIB' => 'Indian Bank',
    'UTIB' => 'Axis Bank',
];

echo "[1/4] Seeding employee salary profiles from sample sheets...\n";
$empMap = []; // code => employee_id

// Combine all employees from various sheets
$allSampleProfiles = [];
foreach ($raw['employees_pf'] as $p) {
    $p['type'] = 'full_time_pf';
    $p['pf_app'] = 1;
    $allSampleProfiles[$p['emp_id']] = $p;
}
foreach ($raw['consultants'] as $p) {
    $p['type'] = 'consultant';
    $p['pf_app'] = 0;
    if (!isset($allSampleProfiles[$p['emp_id']])) {
        $allSampleProfiles[$p['emp_id']] = $p;
    }
}
foreach ($raw['hiring_team'] as $p) {
    $p['type'] = 'full_time_no_pf';
    $p['pf_app'] = 0;
    if (!isset($allSampleProfiles[$p['emp_id']])) {
        $allSampleProfiles[$p['emp_id']] = $p;
    }
}
foreach ($raw['bench_team'] as $p) {
    $p['type'] = 'bench';
    $p['pf_app'] = 1;
    if (!isset($allSampleProfiles[$p['emp_id']])) {
        $allSampleProfiles[$p['emp_id']] = $p;
    }
}

// Add bank data from salaries_aug
$bankDataMap = [];
foreach ($raw['salaries_aug'] as $s) {
    $bankDataMap[$s['emp_id']] = $s;
}

$seededProfiles = 0;
foreach ($allSampleProfiles as $empCode => $p) {
    $name = trim($p['name']);
    $dept = $p['department'] ?? 'Development';
    $desig = $p['designation'] ?? 'Software Engineer';
    $doj = $p['doj'] ?? '2026-02-01';
    $ctc = (float) ($p['ctc'] ?? 35000.00);
    $empType = $p['type'] ?? 'full_time_pf';
    $pfApp = $p['pf_app'] ?? 1;

    $empId = getOrCreateEmployee($db, $empCode, $name, $dept, $desig, $doj, $employeeRoleId);
    $empMap[$empCode] = $empId;

    $bInfo = $bankDataMap[$empCode] ?? [];
    $bankAcc = $p['bank_acc'] ?? ($bInfo['bank_acc'] ?? '');
    $ifsc = $p['ifsc'] ?? ($bInfo['ifsc'] ?? '');
    $bankName = $p['bank_name'] ?? ($bInfo['bank_name'] ?? '');
    if (empty($bankName) && !empty($ifsc)) {
        $prefix = substr($ifsc, 0, 4);
        $bankName = $bankMap[$prefix] ?? 'Scheduled Commercial Bank';
    }

    $uan = $p['uan'] ?? '';
    $pfNum = $p['pf_num'] ?? '';
    $pan = 'ABCDE' . str_pad((string)($empId + 1000), 4, '0', STR_PAD_LEFT) . 'F';

    $salaryStructureModel->saveStructure([
        'employee_id'         => $empId,
        'employment_type'     => $empType,
        'monthly_ctc'         => $ctc,
        'base_salary'         => (float) ($p['basic'] ?? ($ctc * 0.50)),
        'hra'                 => (float) ($p['hra'] ?? ($ctc * 0.25)),
        'special_allowance'   => (float) ($p['special'] ?? 1600.00),
        'other_allowance'     => (float) ($p['other'] ?? 0.00),
        'pf_applicable'       => $pfApp,
        'esi_applicable'      => ($ctc <= 21000.00) ? 1 : 0,
        'pt_applicable'       => 1,
        'bank_account_number' => $bankAcc,
        'bank_name'           => $bankName ?: 'HDFC Bank',
        'bank_ifsc'           => $ifsc ?: 'HDFC0001042',
        'account_holder_name' => $name,
        'pan_number'          => $pan,
        'uan_number'          => $uan,
        'pf_number'           => $pfNum,
        'effective_from'      => '2026-01-01',
        'is_active'           => 1
    ]);
    $seededProfiles++;
}
echo "  [OK] Successfully configured {$seededProfiles} employee salary profiles.\n";

// 3. Ensure existing 100 demo employees also have structures
$existingPop = $salaryStructureModel->autoPopulateDefaultStructures();
echo "  [OK] Default structures ensured for all remaining active staff (+{$existingPop}).\n";

// 4. Create Monthly Historical Runs: April 2026 -> September 2026
$months = [
    ['month' => '2026-04', 'days' => 30, 'pay_date' => '2026-04-30', 'status' => 'disbursed', 'title' => 'April 2026 Payroll'],
    ['month' => '2026-05', 'days' => 31, 'pay_date' => '2026-05-31', 'status' => 'disbursed', 'title' => 'May 2026 Payroll'],
    ['month' => '2026-06', 'days' => 30, 'pay_date' => '2026-06-30', 'status' => 'disbursed', 'title' => 'June 2026 Payroll'],
    ['month' => '2026-07', 'days' => 31, 'pay_date' => '2026-07-31', 'status' => 'disbursed', 'title' => 'July 2026 Payroll'],
    ['month' => '2026-08', 'days' => 31, 'pay_date' => '2026-09-01', 'status' => 'disbursed', 'title' => 'August 2026 Payroll'],
    ['month' => '2026-09', 'days' => 30, 'pay_date' => '2026-10-01', 'status' => 'approved',  'title' => 'September 2026 Payroll'],
];

echo "\n[2/4] Generating and seeding monthly payroll runs (April 2026 - September 2026)...\n";
$adminUserId = 1; // admin user

foreach ($months as $m) {
    $runId = $payrollModel->createRun($m['month'], $m['days'], $m['pay_date'], $adminUserId, $m['title']);
    $res = $payrollModel->generateMonthlyPayroll($runId);
    echo "  - {$m['title']} (Run ID: {$runId}): {$res['count']} payslips generated.\n";

    // Set run status & approver
    if ($m['status'] === 'disbursed') {
        $payrollModel->approveRun($runId, $adminUserId, 'Approved by Administrator.');
        $payrollModel->disburseRun($runId);
    } elseif ($m['status'] === 'approved') {
        $payrollModel->submitRun($runId, $adminUserId);
        $payrollModel->approveRun($runId, $adminUserId, 'Approved by Administrator.');
    }
}

// 5. Apply exact August adjustments from the sample file
echo "\n[3/4] Applying sample LOP and variable bonus adjustments for August 2026...\n";
$augRun = $payrollModel->getRunByMonth('2026-08');
if ($augRun) {
    $runId = (int) $augRun->payroll_run_id;

    // Apply LOP for specific employees from LOP sheet
    foreach ($raw['lop_aug'] as $code => $lopDays) {
        if (isset($empMap[$code])) {
            $empId = $empMap[$code];
            $stmt = $db->prepare("SELECT item_id FROM payroll_items WHERE payroll_run_id = :run_id AND employee_id = :emp_id");
            $stmt->execute([':run_id' => $runId, ':emp_id' => $empId]);
            $itemId = $stmt->fetchColumn();
            if ($itemId) {
                $paidDays = max(0, 31 - (float)$lopDays);
                $payrollModel->updateItemAdjustments((int)$itemId, ['paid_days' => $paidDays]);
                echo "    + Applied {$lopDays} days LOP for {$code}\n";
            }
        }
    }

    // Apply variable additions from Employees sheet (bonus, arrears, etc.)
    foreach ($raw['employees_pf'] as $p) {
        $code = $p['emp_id'];
        $bonus = (float) ($p['bonus'] ?? 0);
        $arrears = (float) ($p['arrears'] ?? 0);
        $reimb = (float) ($p['reimb'] ?? 0);
        $tds = (float) ($p['tds'] ?? 0);

        if (($bonus > 0 || $arrears > 0 || $reimb > 0 || $tds > 0) && isset($empMap[$code])) {
            $empId = $empMap[$code];
            $stmt = $db->prepare("SELECT item_id FROM payroll_items WHERE payroll_run_id = :run_id AND employee_id = :emp_id");
            $stmt->execute([':run_id' => $runId, ':emp_id' => $empId]);
            $itemId = $stmt->fetchColumn();
            if ($itemId) {
                $payrollModel->updateItemAdjustments((int)$itemId, [
                    'bonus_incentive' => $bonus,
                    'arrears'         => $arrears,
                    'reimbursements'  => $reimb,
                    'tds'             => $tds
                ]);
            }
        }
    }

    // Set hold remarks on any flagged items
    foreach ($raw['salaries_aug'] as $s) {
        if (!empty($s['remarks']) && stripos($s['remarks'], 'hold') !== false && isset($empMap[$s['emp_id']])) {
            $empId = $empMap[$s['emp_id']];
            $stmt = $db->prepare("UPDATE payroll_items SET payment_status = 'hold', payment_remarks = 'On Hold per Management' WHERE payroll_run_id = :run_id AND employee_id = :emp_id");
            $stmt->execute([':run_id' => $runId, ':emp_id' => $empId]);
            echo "    + Payment placed on hold for {$s['emp_id']}\n";
        }
    }

    $payrollModel->recalculateRunTotals($runId);
}

// 6. Seed Trainer Hourly Logs
echo "\n[4/4] Seeding Trainer hourly payment logs...\n";
if ($augRun && !empty($raw['trainers'])) {
    foreach ($raw['trainers'] as $tr) {
        $stmt = $db->prepare("INSERT INTO payroll_hourly_logs (
                                payroll_run_id, trainer_name, unit_type, units, rate_per_unit, total_cost,
                                bank_account_number, bank_ifsc, account_holder_name, bank_name, payment_status
                              ) VALUES (
                                :run_id, :name, :unit_type, :units, :rate, :total,
                                :bank_acc, :ifsc, :holder, :bank_name, 'paid'
                              )");
        $stmt->execute([
            ':run_id'    => $augRun->payroll_run_id,
            ':name'      => $tr['name'],
            ':unit_type' => strtolower($tr['unit_type']) === 'days' ? 'days' : 'hours',
            ':units'     => (float) $tr['units'],
            ':rate'      => (float) $tr['rate'],
            ':total'     => (float) $tr['total'],
            ':bank_acc'  => $tr['bank_acc'],
            ':ifsc'      => $tr['ifsc'],
            ':holder'    => $tr['holder'] ?: $tr['name'],
            ':bank_name' => $tr['bank_name'] ?: 'Axis Bank'
        ]);
        echo "    + Trainer log: {$tr['name']} ({$tr['units']} {$tr['unit_type']} @ ₹{$tr['rate']} = ₹{$tr['total']})\n";
    }
}

echo "\n[DONE] All sample payroll data for previous months (April 2026 - September 2026) has been successfully seeded!\n";
