<?php
require_once dirname(__DIR__) . '/core/PayrollReadinessEngine.php';

class PayrollController extends Controller {
    private $payrollModel;
    private $salaryStructureModel;

    public function __construct() {
        $this->requireAuth();
        $this->payrollModel = $this->model('Payroll');
        $this->salaryStructureModel = $this->model('SalaryStructure');
    }

    /**
     * Payroll Dashboard / Monthly Runs Overview.
     */
    public function index() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $runs = $this->payrollModel->getRuns();
        $stats = $this->payrollModel->getSummaryStats();

        $data = [
            'title'      => 'Payroll Management | Raptor CRM',
            'active_tab' => 'payroll',
            'runs'       => $runs,
            'stats'      => $stats,
        ];

        $this->viewWithLayout('payroll/index', 'main', $data);
    }

    /**
     * Payroll Readiness Control Center (Pre-Processing Validation & Checkpoints).
     */
    public function readiness() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $payMonth = trim($_GET['month'] ?? date('Y-m'));

        // Run automated evaluation if not evaluated or requested
        if (isset($_GET['evaluate']) || empty($_SESSION['readiness_eval_' . $payMonth])) {
            PayrollReadinessEngine::evaluateMonth($payMonth, (int)$_SESSION['user_id']);
            $_SESSION['readiness_eval_' . $payMonth] = true;
        }

        $summary = PayrollReadinessEngine::getCheckpointsSummary($payMonth);

        // Fetch workforce stats for selected month
        $db = Database::getInstance()->getConnection();
        $startDate = $payMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $stmt = $db->query("SELECT COUNT(*) FROM employees e JOIN users u ON e.user_id = u.user_id WHERE u.status = 'active'");
        $totalActive = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE hire_date BETWEEN :s AND :e");
        $stmt->execute([':s' => $startDate, ':e' => $endDate]);
        $newJoinersCount = (int) $stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status IN ('inactive', 'terminated', 'resigned')");
        $exitedCount = (int) $stmt->fetchColumn();

        $prevMonth = date('Y-m', strtotime('-1 month', strtotime($startDate)));
        $stmt = $db->prepare("SELECT total_net_pay, total_employees FROM payroll_runs WHERE pay_month = :m");
        $stmt->execute([':m' => $prevMonth]);
        $prevRun = $stmt->fetch(PDO::FETCH_OBJ);

        // Active employee list for dropdowns
        $stmt = $db->query("SELECT e.employee_id, e.employee_code, u.name as employee_name, e.department, ss.monthly_ctc
                            FROM employees e
                            JOIN users u ON e.user_id = u.user_id
                            LEFT JOIN salary_structures ss ON e.employee_id = ss.employee_id
                            WHERE u.status = 'active'
                            ORDER BY u.name ASC");
        $employeesList = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = [
            'title'              => "Payroll Readiness & Pre-Processing Validation ({$payMonth})",
            'active_tab'         => 'payroll',
            'pay_month'          => $payMonth,
            'summary'            => $summary,
            'total_active'       => $totalActive,
            'new_joiners_count'  => $newJoinersCount,
            'exited_count'       => $exitedCount,
            'prev_run'           => $prevRun,
            'prev_month'         => $prevMonth,
            'employees_list'     => $employeesList,
            'checkpoints_def'    => PayrollReadinessEngine::CHECKPOINTS
        ];

        $this->viewWithLayout('payroll/readiness', 'main', $data);
    }

    /**
     * Trigger re-evaluation of all checkpoints for a month.
     */
    public function evaluateReadiness() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $payMonth = trim($_POST['pay_month'] ?? date('Y-m'));
        PayrollReadinessEngine::evaluateMonth($payMonth, (int)$_SESSION['user_id']);
        $this->audit("Evaluated pre-payroll readiness checkpoints for {$payMonth}", 'payroll_readiness', 0);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $summary = PayrollReadinessEngine::getCheckpointsSummary($payMonth);
            $this->jsonOk($summary, "Payroll readiness evaluated for {$payMonth}.");
        }

        $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
    }

    /**
     * Add manual pre-payroll adjustment (LOP, Incentive, Deduction, Bonus, Arrear).
     */
    public function addAdjustment() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $payMonth = trim($_POST['pay_month'] ?? date('Y-m'));
            $empId = (int)($_POST['employee_id'] ?? 0);
            $category = trim($_POST['category'] ?? 'lop');
            $amount = (float)($_POST['amount'] ?? 0);
            $days = (float)($_POST['days'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($empId > 0 && !empty($reason)) {
                PayrollReadinessEngine::addAdjustment([
                    'pay_month'   => $payMonth,
                    'employee_id' => $empId,
                    'category'    => $category,
                    'amount'      => $amount,
                    'days'        => $days,
                    'reason'      => $reason,
                    'remarks'     => $remarks,
                    'source'      => 'manual_entry'
                ], (int)$_SESSION['user_id']);

                $this->audit("Added manual {$category} adjustment for employee ID {$empId} in {$payMonth}", 'payroll_adjustments_pre', $empId);
                PayrollReadinessEngine::evaluateMonth($payMonth, (int)$_SESSION['user_id']);
            }
            $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
        }
        $this->redirect('index.php?route=payroll/readiness');
    }

    /**
     * Download predefined standard CSV sample templates.
     */
    public function downloadTemplate($category = 'lop') {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $category = preg_replace('/[^a-zA-Z0-9_-]/', '', $category);
        $csv = PayrollReadinessEngine::generateSampleTemplate($category);
        $filename = "payroll_template_{$category}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit();
    }

    /**
     * Handle bulk CSV uploads with row-by-row validation.
     */
    public function uploadTemplate() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['template_file'])) {
            $payMonth = trim($_POST['pay_month'] ?? date('Y-m'));
            $category = trim($_POST['category'] ?? 'lop');
            $file = $_FILES['template_file'];

            if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                $res = PayrollReadinessEngine::processBulkUpload($payMonth, $category, $file['tmp_name'], (int)$_SESSION['user_id']);
                $this->audit("Bulk uploaded {$category} CSV for {$payMonth}: {$res['valid_count']} valid", 'payroll_adjustments_pre', 0);

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $this->jsonOk($res, $res['message']);
                }

                $_SESSION['upload_result'] = $res;
            }
            $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
        }
        $this->redirect('index.php?route=payroll/readiness');
    }

    /**
     * Resolve / Override an individual exception.
     */
    public function resolveException() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $excId = (int)($_POST['exception_id'] ?? 0);
            $payMonth = trim($_POST['pay_month'] ?? date('Y-m'));
            $reason = trim($_POST['resolution_reason'] ?? 'Approved by HR');

            if ($excId > 0 && !empty($reason)) {
                PayrollReadinessEngine::resolveException($excId, (int)$_SESSION['user_id'], $reason);
                $this->audit("Resolved payroll exception ID {$excId}: {$reason}", 'payroll_exceptions', $excId);
                PayrollReadinessEngine::evaluateMonth($payMonth, (int)$_SESSION['user_id']);
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->jsonOk(null, 'Exception marked as resolved.');
            }

            $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
        }
        $this->redirect('index.php?route=payroll/readiness');
    }

    /**
     * Gated Payroll Execution endpoint.
     * Enforces that ALL mandatory checkpoints have passed and 0 blocking errors exist.
     */
    public function executeRun() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $payMonth = trim($_POST['pay_month'] ?? date('Y-m'));
            $calendarDays = (int) ($_POST['calendar_days'] ?? date('t', strtotime($payMonth . '-01')));
            $payDate = trim($_POST['pay_date'] ?? date('Y-m-01', strtotime('+1 month', strtotime($payMonth . '-01'))));
            $title = trim($_POST['title'] ?? date('F Y', strtotime($payMonth . '-01')) . ' Payroll');

            // 1. Mandatory Gate Verification
            $eval = PayrollReadinessEngine::evaluateMonth($payMonth, (int)$_SESSION['user_id']);
            if ($eval['blocking_errors'] > 0) {
                $_SESSION['payroll_gate_error'] = "Payroll cannot be processed: {$eval['blocking_errors']} blocking issues require your resolution before payroll can run.";
                $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
                return;
            }

            // 2. Ensure salary structures
            $this->salaryStructureModel->autoPopulateDefaultStructures();

            // 3. Create run and calculate
            $runId = $this->payrollModel->createRun($payMonth, $calendarDays, $payDate, (int)$_SESSION['user_id'], $title);
            $this->payrollModel->generateMonthlyPayroll($runId);

            $this->audit("Successfully validated and executed monthly payroll run {$payMonth}", 'payroll_runs', $runId);
            $this->redirect("index.php?route=payroll/run/{$runId}");
        }

        $this->redirect('index.php?route=payroll/readiness');
    }

    /**
     * Create a new monthly payroll run.
     */
    public function create() {
        // Redirect to Readiness Control Center
        $payMonth = trim($_GET['month'] ?? date('Y-m'));
        $this->redirect("index.php?route=payroll/readiness&month={$payMonth}");
    }

    /**
     * View detailed monthly payroll register with filters and search.
     *
     * @param int $runId
     */
    public function run($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $runId = (int) $runId;
        $run = $this->payrollModel->getRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/index');
        }

        $typeFilter = $_GET['type'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $filters = [
            'employment_type' => $typeFilter,
            'payment_status'  => $statusFilter,
            'search'          => $search
        ];

        $items = $this->payrollModel->getPayrollItems($runId, $filters);

        $data = [
            'title'         => $run->title . ' | Raptor Payroll',
            'active_tab'    => 'payroll',
            'run'           => $run,
            'items'         => $items,
            'type_filter'   => $typeFilter,
            'status_filter' => $statusFilter,
            'search'        => $search,
        ];

        $this->viewWithLayout('payroll/run', 'main', $data);
    }

    /**
     * Calculate / Recalculate monthly payroll items.
     *
     * @param int $runId
     */
    public function generate($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $runId = (int) $runId;
        $this->salaryStructureModel->autoPopulateDefaultStructures();
        $res = $this->payrollModel->generateMonthlyPayroll($runId);

        $this->audit("Recalculated payroll run {$runId}", 'payroll_runs', $runId);
        $this->redirect("index.php?route=payroll/run/{$runId}");
    }

    /**
     * Update variable adjustments on an individual payslip.
     *
     * @param int $itemId
     */
    public function adjustItem($itemId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $itemId = (int) $itemId;
        $item = $this->payrollModel->getItemById($itemId);
        if (!$item) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'paid_days'        => (float) ($_POST['paid_days'] ?? $item->paid_days),
                'arrears'          => (float) ($_POST['arrears'] ?? 0.00),
                'reimbursements'   => (float) ($_POST['reimbursements'] ?? 0.00),
                'bonus_incentive'  => (float) ($_POST['bonus_incentive'] ?? 0.00),
                'tds'              => (float) ($_POST['tds'] ?? 0.00),
                'other_deductions' => (float) ($_POST['other_deductions'] ?? 0.00),
                'payment_status'   => $_POST['payment_status'] ?? $item->payment_status,
                'payment_remarks'  => trim($_POST['payment_remarks'] ?? ''),
            ];

            $this->payrollModel->updateItemAdjustments($itemId, $data);
            $this->audit("Adjusted payslip for {$item->employee_name}", 'payroll_items', $itemId);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->jsonOk(null, 'Payslip adjustments saved successfully.');
            }
        }

        $this->redirect("index.php?route=payroll/run/{$item->payroll_run_id}");
    }

    /**
     * Submit draft run for approval (HR/Admin).
     *
     * @param int $runId
     */
    public function submitRun($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $runId = (int) $runId;
        $this->payrollModel->submitRun($runId, (int) $_SESSION['user_id']);
        $this->audit("Submitted payroll run {$runId} for approval", 'payroll_runs', $runId);
        $this->redirect("index.php?route=payroll/run/{$runId}");
    }

    /**
     * Approve submitted run (Admin only).
     *
     * @param int $runId
     */
    public function approve($runId = null) {
        if ($_SESSION['user_role'] !== 'admin') {
            $this->redirect('index.php?route=payroll/index');
        }

        $runId = (int) $runId;
        $remarks = trim($_POST['approval_remarks'] ?? 'Approved by Administrator.');
        $this->payrollModel->approveRun($runId, (int) $_SESSION['user_id'], $remarks);
        $this->audit("Approved payroll run {$runId}", 'payroll_runs', $runId);
        $this->redirect("index.php?route=payroll/run/{$runId}");
    }

    /**
     * Disburse payroll run (Admin only).
     *
     * @param int $runId
     */
    public function disburse($runId = null) {
        if ($_SESSION['user_role'] !== 'admin') {
            $this->redirect('index.php?route=payroll/index');
        }

        $runId = (int) $runId;
        $this->payrollModel->disburseRun($runId);
        $this->audit("Disbursed payroll run {$runId}", 'payroll_runs', $runId);
        $this->redirect("index.php?route=payroll/run/{$runId}");
    }

    /**
     * Employee Salary Profiles Directory.
     */
    public function structures() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        // Auto-seed missing structures
        $this->salaryStructureModel->autoPopulateDefaultStructures();

        $search = $_GET['search'] ?? '';
        $empType = $_GET['type'] ?? '';

        $filters = [
            'search'          => $search,
            'employment_type' => $empType
        ];

        $structures = $this->salaryStructureModel->getAllStructures($filters);
        $revisionsGrouped = $this->salaryStructureModel->getAllRevisionsGrouped();

        $data = [
            'title'             => 'Salary Structures & Revision History | Raptor CRM',
            'active_tab'        => 'salary_structures',
            'structures'        => $structures,
            'revisions_grouped' => $revisionsGrouped,
            'search'            => $search,
            'emp_type'          => $empType
        ];

        $this->viewWithLayout('payroll/structures', 'main', $data);
    }

    /**
     * Save / Update Employee Salary Structure with Revision History Tracking.
     */
    public function saveStructure() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $empId = (int) ($_POST['employee_id'] ?? 0);
            if ($empId > 0) {
                $effectiveDate = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : (!empty($_POST['effective_from']) ? trim($_POST['effective_from']) : date('Y-m-d'));
                $effectivePayMonth = !empty($_POST['effective_pay_month']) ? trim($_POST['effective_pay_month']) : date('Y-m', strtotime($effectiveDate));
                $revisionReason = trim($_POST['revision_reason'] ?? '');
                $requiresArrears = isset($_POST['requires_arrears']) ? 1 : 0;

                $data = [
                    'employee_id'         => $empId,
                    'employment_type'     => $_POST['employment_type'] ?? 'full_time_pf',
                    'monthly_ctc'         => (float) ($_POST['monthly_ctc'] ?? 0.00),
                    'base_salary'         => (float) ($_POST['base_salary'] ?? 0.00),
                    'hra'                 => (float) ($_POST['hra'] ?? 0.00),
                    'special_allowance'   => (float) ($_POST['special_allowance'] ?? 1600.00),
                    'other_allowance'     => (float) ($_POST['other_allowance'] ?? 0.00),
                    'pf_applicable'       => isset($_POST['pf_applicable']) ? 1 : 0,
                    'esi_applicable'      => isset($_POST['esi_applicable']) ? 1 : 0,
                    'pt_applicable'       => isset($_POST['pt_applicable']) ? 1 : 0,
                    'bank_account_number' => trim($_POST['bank_account_number'] ?? ''),
                    'bank_name'           => trim($_POST['bank_name'] ?? ''),
                    'bank_ifsc'           => strtoupper(trim($_POST['bank_ifsc'] ?? '')),
                    'account_holder_name' => trim($_POST['account_holder_name'] ?? ''),
                    'pan_number'          => strtoupper(trim($_POST['pan_number'] ?? '')),
                    'uan_number'          => trim($_POST['uan_number'] ?? ''),
                    'pf_number'           => trim($_POST['pf_number'] ?? ''),
                    'effective_date'      => $effectiveDate,
                    'effective_pay_month' => $effectivePayMonth,
                    'revision_reason'     => $revisionReason,
                    'requires_arrears'    => $requiresArrears,
                    'source'              => 'manual_entry',
                    'is_active'           => 1
                ];

                $this->salaryStructureModel->saveStructure($data, (int)$_SESSION['user_id']);
                $this->audit("Updated salary structure & revision history for employee ID {$empId}", 'salary_structures', $empId);
                $_SESSION['salary_structure_success'] = "Salary profile and revision history successfully saved for employee ID {$empId}.";
            }
        }

        $this->redirect('index.php?route=payroll/structures');
    }

    /**
     * Fetch salary revision history for an employee (JSON/AJAX or View).
     *
     * @param int $employeeId
     */
    public function revisionHistory($employeeId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $employeeId = (int)$employeeId;
        $history = $this->salaryStructureModel->getRevisionHistory($employeeId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->jsonOk($history, 'Salary revision history fetched.');
        }

        $this->redirect('index.php?route=payroll/structures');
    }

    /**
     * View / Print a single itemized payslip.
     *
     * @param int $itemId
     */
    public function payslip($itemId = null) {
        $itemId = (int) $itemId;
        $item = $this->payrollModel->getItemById($itemId);
        if (!$item) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        // Security authorization check:
        // If not Admin/HR with manage_payroll permission, user can ONLY view their own payslip!
        $currentUserId = (int) $_SESSION['user_id'];
        $canManage = Policy::canManageEmployees() || $this->hasPermission('manage_payroll');
        if (!$canManage && (int)$item->user_id !== $currentUserId) {
            $this->viewWithLayout('errors/403', 'main', [
                'title'   => 'Access Denied',
                'message' => 'You are not authorized to view this employee payslip.'
            ]);
            return;
        }

        $data = [
            'title'      => 'Payslip — ' . $item->employee_name . ' (' . $item->pay_month . ')',
            'active_tab' => $canManage ? 'payroll' : 'my_payslips',
            'item'       => $item,
            'can_manage' => $canManage
        ];

        $this->viewWithLayout('payroll/payslip', 'main', $data);
    }

    /**
     * Employee Self-Service: My Payslips.
     */
    public function myPayslips() {
        $userId = (int) $_SESSION['user_id'];
        $payslips = $this->payrollModel->getPayslipsForUser($userId);

        $data = [
            'title'      => 'My Payslips | Raptor CRM',
            'active_tab' => 'my_payslips',
            'payslips'   => $payslips,
        ];

        $this->viewWithLayout('payroll/my_payslips', 'main', $data);
    }

    /**
     * Bank Disbursement Export View.
     *
     * @param int $runId
     */
    public function bankExport($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $runId = (int) $runId;
        $run = $this->payrollModel->getRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/index');
        }

        $items = $this->payrollModel->getPayrollItems($runId);

        $data = [
            'title'      => 'Bank Disbursement Export — ' . $run->title,
            'active_tab' => 'payroll',
            'run'        => $run,
            'items'      => $items,
        ];

        $this->viewWithLayout('payroll/bank_export', 'main', $data);
    }

    /**
     * Download Bank Transfer CSV File.
     *
     * @param int $runId
     */
    public function bankExportCsv($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $runId = (int) $runId;
        $run = $this->payrollModel->getRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/index');
        }

        $items = $this->payrollModel->getPayrollItems($runId);

        $filename = 'bank_disbursement_' . $run->pay_month . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // CSV Header
        fputcsv($out, [
            'S.No', 'Employee Code', 'Employee Name', 'Account Holder Name',
            'Bank Name', 'Account Number', 'IFSC Code', 'Net Salary Payable',
            'Payment Status', 'Remarks'
        ]);

        $idx = 1;
        foreach ($items as $it) {
            fputcsv($out, [
                $idx++,
                $it->employee_code,
                $it->employee_name,
                $it->account_holder_name ?: $it->employee_name,
                $it->bank_name,
                $it->bank_account_number,
                $it->bank_ifsc,
                number_format($it->net_salary, 2, '.', ''),
                strtoupper($it->payment_status),
                $it->payment_remarks ?: 'Salary Payout ' . $run->pay_month
            ]);
        }
        fclose($out);
        exit();
    }

    /**
     * Toggle individual employee's payslip eligibility (HR selection).
     *
     * @param int $itemId
     */
    public function toggleEligibility($itemId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $itemId = (int) $itemId;
        $item = $this->payrollModel->getItemById($itemId);
        if (!$item) {
            $this->redirect('index.php?route=payroll/index');
        }

        $this->payrollModel->toggleItemEligibility($itemId);
        $this->audit("Toggled payslip eligibility for {$item->employee_name}", 'payroll_items', $itemId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $updated = $this->payrollModel->getItemById($itemId);
            $this->jsonOk([
                'is_eligible' => (int) $updated->is_eligible_payslip,
                'message' => $updated->is_eligible_payslip ? 'Employee enabled for payslip' : 'Employee excluded from payslip'
            ]);
        }

        $this->redirect("index.php?route=payroll/run/{$item->payroll_run_id}");
    }

    /**
     * Batch update payslip eligibility for multiple employees.
     */
    public function batchEligibility() {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $runId = (int) ($_POST['payroll_run_id'] ?? 0);
            $action = $_POST['batch_action'] ?? 'include'; // 'include' or 'exclude'
            $itemIds = $_POST['item_ids'] ?? [];

            if ($runId > 0 && is_array($itemIds) && !empty($itemIds)) {
                $eligible = ($action === 'include') ? 1 : 0;
                $this->payrollModel->batchSetEligibility($runId, $itemIds, $eligible);
                $this->audit("Batch updated payslip eligibility for " . count($itemIds) . " employees to {$action}", 'payroll_runs', $runId);
            }
            $this->redirect("index.php?route=payroll/run/{$runId}");
        }

        $this->redirect('index.php?route=payroll/index');
    }

    /**
     * Dispatch / Email an individual payslip to the employee.
     *
     * @param int $itemId
     */
    public function sendEmail($itemId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $itemId = (int) $itemId;
        $item = $this->payrollModel->getItemById($itemId);
        if (!$item) {
            $this->redirect('index.php?route=payroll/index');
        }

        // Record dispatch timestamp
        $this->payrollModel->recordEmailSent($itemId);
        $this->audit("Sent payslip email to {$item->employee_name} ({$item->employee_email}) for {$item->pay_month}", 'payroll_items', $itemId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->jsonOk([
                'sent_at' => date('d M Y, h:i A'),
                'recipient' => $item->employee_email,
                'message' => "Payslip for {$item->employee_name} successfully dispatched to {$item->employee_email}."
            ]);
        }

        $this->redirect("index.php?route=payroll/payslip/{$itemId}&email_sent=1");
    }

    /**
     * Dispatch payslips via email to all eligible employees in a run.
     *
     * @param int $runId
     */
    public function sendBatchEmails($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/index');
        }

        $runId = (int) $runId;
        $run = $this->payrollModel->getRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/index');
        }

        $items = $this->payrollModel->getRunItemsForBatch($runId, true);
        $sentCount = 0;
        foreach ($items as $it) {
            $this->payrollModel->recordEmailSent((int) $it->item_id);
            $sentCount++;
        }

        $this->audit("Batch sent {$sentCount} payslip emails for {$run->title}", 'payroll_runs', $runId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->jsonOk([
                'sent_count' => $sentCount,
                'message' => "Successfully sent payslip notifications to {$sentCount} employees."
            ]);
        }

        $this->redirect("index.php?route=payroll/run/{$runId}&batch_emails_sent={$sentCount}");
    }

    /**
     * Download standalone payslip HTML file.
     *
     * @param int $itemId
     */
    public function downloadPayslip($itemId = null) {
        $itemId = (int) $itemId;
        $item = $this->payrollModel->getItemById($itemId);
        if (!$item) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $currentUserId = (int) $_SESSION['user_id'];
        $canManage = Policy::canManageEmployees() || $this->hasPermission('manage_payroll');
        if (!$canManage && (int)$item->user_id !== $currentUserId) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item->employee_name);
        $filename = "Payslip_{$item->employee_code}_{$item->pay_month}_{$safeName}.html";

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Render standalone self-contained payslip view
        require_once dirname(__DIR__) . '/views/payroll/payslip_standalone.php';
        exit();
    }

    /**
     * Multi-payslip printable pack (batch printable / PDF for entire run).
     *
     * @param int $runId
     */
    public function printBatch($runId = null) {
        if (!Policy::canManageEmployees() && !$this->hasPermission('manage_payroll')) {
            $this->redirect('index.php?route=payroll/myPayslips');
        }

        $runId = (int) $runId;
        $run = $this->payrollModel->getRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/index');
        }

        $items = $this->payrollModel->getRunItemsForBatch($runId, true);

        $data = [
            'title' => 'Payslip Batch Pack — ' . $run->title,
            'run'   => $run,
            'items' => $items
        ];

        require_once dirname(__DIR__) . '/views/payroll/batch_print.php';
        exit();
    }
}

