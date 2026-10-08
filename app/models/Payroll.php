<?php
/**
 * Raptor CRM — Payroll Model
 * -----------------------------------------------------------------------------
 * Manages monthly payroll runs, automated attendance-based calculations,
 * itemized payslips, approval governance, and disbursement records.
 */

class Payroll extends Model {

    /**
     * Get all payroll runs.
     *
     * @param array $filters (status, year)
     * @return array
     */
    public function getRuns(array $filters = []): array {
        $sql = "SELECT pr.*,
                       u.name as creator_name,
                       appr.name as approver_name
                FROM payroll_runs pr
                JOIN users u ON pr.created_by = u.user_id
                LEFT JOIN users appr ON pr.approved_by = appr.user_id
                WHERE 1=1";

        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['year'])) {
            $sql .= " AND pr.pay_month LIKE :yr";
            $params[':yr'] = $filters['year'] . '-%';
        }

        $sql .= " ORDER BY pr.pay_month DESC";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet();
    }

    /**
     * Get single payroll run by ID.
     *
     * @param int $runId
     * @return object|null
     */
    public function getRunById(int $runId) {
        $sql = "SELECT pr.*,
                       u.name as creator_name,
                       appr.name as approver_name
                FROM payroll_runs pr
                JOIN users u ON pr.created_by = u.user_id
                LEFT JOIN users appr ON pr.approved_by = appr.user_id
                WHERE pr.payroll_run_id = :id
                LIMIT 1";
        $this->query($sql);
        $this->bind(':id', $runId);
        return $this->single() ?: null;
    }

    /**
     * Get single payroll run by month (YYYY-MM).
     *
     * @param string $payMonth
     * @return object|null
     */
    public function getRunByMonth(string $payMonth) {
        $sql = "SELECT pr.*, u.name as creator_name
                FROM payroll_runs pr
                JOIN users u ON pr.created_by = u.user_id
                WHERE pr.pay_month = :m
                LIMIT 1";
        $this->query($sql);
        $this->bind(':m', $payMonth);
        return $this->single() ?: null;
    }

    /**
     * Create a new monthly payroll run.
     *
     * @param string $payMonth (YYYY-MM)
     * @param int    $calendarDays
     * @param string $payDate (YYYY-MM-DD)
     * @param int    $createdBy (user_id)
     * @param string $title
     * @return int
     */
    public function createRun(string $payMonth, int $calendarDays, string $payDate, int $createdBy, string $title = ''): int {
        if (empty($title)) {
            $dateObj = DateTime::createFromFormat('Y-m', $payMonth);
            $title = ($dateObj ? $dateObj->format('F Y') : $payMonth) . ' Payroll';
        }

        $sql = "INSERT INTO payroll_runs (pay_month, title, calendar_days, pay_date, status, created_by)
                VALUES (:m, :title, :days, :pay_date, 'draft', :uid)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    calendar_days = VALUES(calendar_days),
                    pay_date = VALUES(pay_date)";

        $this->query($sql);
        $this->bind(':m', $payMonth);
        $this->bind(':title', $title);
        $this->bind(':days', $calendarDays);
        $this->bind(':pay_date', $payDate);
        $this->bind(':uid', $createdBy);
        $this->execute();

        $existing = $this->getRunByMonth($payMonth);
        return $existing ? (int) $existing->payroll_run_id : (int) $this->lastInsertId();
    }

    /**
     * Automatically compute and populate all payslips for a payroll run
     * using employee salary structures and attendance records.
     *
     * @param int $runId
     * @return array ['success' => bool, 'count' => int, 'message' => string]
     */
    public function generateMonthlyPayroll(int $runId): array {
        $run = $this->getRunById($runId);
        if (!$run) {
            return ['success' => false, 'count' => 0, 'message' => 'Payroll run not found.'];
        }

        if (in_array($run->status, ['approved', 'disbursed'], true)) {
            return ['success' => false, 'count' => 0, 'message' => 'Cannot regenerate an approved or disbursed payroll run.'];
        }

        $payMonth = $run->pay_month;
        $calendarDays = (int) $run->calendar_days;
        $startDate = $payMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        // 1. Get all active employees with salary structures
        $this->query("SELECT ss.*, e.employee_id, e.user_id, u.name as employee_name, u.email
                      FROM salary_structures ss
                      JOIN employees e ON ss.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      WHERE ss.is_active = 1 AND u.status = 'active'");
        $structures = $this->resultSet();

        if (empty($structures)) {
            return ['success' => false, 'count' => 0, 'message' => 'No active salary structures found. Please configure salary structures first.'];
        }

        // 2. Fetch attendance stats for each user in this month
        $this->query("SELECT user_id, COUNT(DISTINCT work_date) as days_present
                      FROM attendance
                      WHERE work_date BETWEEN :start AND :end
                        AND (login_at IS NOT NULL OR worked_minutes > 0 OR status IN ('present', 'wfh', 'half_day'))
                      GROUP BY user_id");
        $this->bind(':start', $startDate);
        $this->bind(':end', $endDate);
        $attendanceMap = [];
        foreach ($this->resultSet() as $att) {
            $attendanceMap[(int) $att->user_id] = (int) $att->days_present;
        }

        $processedCount = 0;
        $totalGross = 0.00;
        $totalDeductions = 0.00;
        $totalNet = 0.00;
        $totalEmployerCost = 0.00;

        // Fetch pre-payroll adjustments for this month
        $this->query("SELECT employee_id, category, SUM(amount) as sum_amt, SUM(days) as sum_days
                      FROM payroll_adjustments_pre
                      WHERE pay_month = :m AND status = 'approved'
                      GROUP BY employee_id, category");
        $this->bind(':m', $payMonth);
        $preAdjMap = [];
        foreach ($this->resultSet() as $pa) {
            $eId = (int)$pa->employee_id;
            if (!isset($preAdjMap[$eId])) $preAdjMap[$eId] = [];
            $preAdjMap[$eId][$pa->category] = [
                'amount' => (float)$pa->sum_amt,
                'days'   => (float)$pa->sum_days
            ];
        }

        // Fetch approved salary revisions for this month
        $this->query("SELECT * FROM salary_revisions WHERE effective_pay_month = :m AND status = 'approved'");
        $this->bind(':m', $payMonth);
        $revisionMap = [];
        foreach ($this->resultSet() as $rev) {
            $revisionMap[(int)$rev->employee_id] = $rev;
        }

        foreach ($structures as $st) {
            $empId = (int) $st->employee_id;
            $userId = (int) $st->user_id;

            // Apply salary revision if exists
            if (isset($revisionMap[$empId])) {
                $rev = $revisionMap[$empId];
                $st->monthly_ctc = (float)$rev->revised_ctc;
                // Recompute standard split for revised CTC
                $breakup = PayrollEngine::calculateStandardSalaryBreakup($st->monthly_ctc, (string)$st->employment_type);
                $st->base_salary = $breakup['base_salary'];
                $st->hra = $breakup['hra'];
                $st->special_allowance = $breakup['special_allowance'];
                $st->other_allowance = $breakup['other_allowance'];
            }

            // Attendance & LOP calculation:
            $preLopDays = isset($preAdjMap[$empId]['lop']) ? (float)$preAdjMap[$empId]['lop']['days'] : 0.00;

            if (isset($attendanceMap[$userId]) && $attendanceMap[$userId] > 0) {
                $paidDays = min($calendarDays, (float) $attendanceMap[$userId]);
                if ($paidDays >= 20 && $calendarDays >= 28) {
                    $paidDays = (float) $calendarDays;
                }
                $lopDays = max(0, $calendarDays - $paidDays);
            } else {
                $lopDays = $preLopDays;
                $paidDays = max(0, (float) $calendarDays - $lopDays);
            }

            // Merge adjustments (pre-payroll verified adjustments + existing item adjustments)
            $this->query("SELECT arrears, reimbursements, bonus_incentive, tds, other_deductions, payment_status, payment_remarks
                          FROM payroll_items
                          WHERE payroll_run_id = :run_id AND employee_id = :emp_id");
            $this->bind(':run_id', $runId);
            $this->bind(':emp_id', $empId);
            $existingItem = $this->single();

            $preIncentives = isset($preAdjMap[$empId]['incentive']) ? (float)$preAdjMap[$empId]['incentive']['amount'] : 0.00;
            $preBonus = isset($preAdjMap[$empId]['bonus']) ? (float)$preAdjMap[$empId]['bonus']['amount'] : 0.00;
            $preDeductions = isset($preAdjMap[$empId]['deduction']) ? (float)$preAdjMap[$empId]['deduction']['amount'] : 0.00;
            $preArrears = isset($preAdjMap[$empId]['arrears']) ? (float)$preAdjMap[$empId]['arrears']['amount'] : 0.00;

            if (isset($revisionMap[$empId]) && $revisionMap[$empId]->requires_arrears) {
                $preArrears += (float)$revisionMap[$empId]->difference_amount;
            }

            $adj = [
                'arrears'          => ($existingItem ? (float) $existingItem->arrears : 0.00) + $preArrears,
                'reimbursements'   => $existingItem ? (float) $existingItem->reimbursements : 0.00,
                'bonus_incentive'  => ($existingItem ? (float) $existingItem->bonus_incentive : 0.00) + $preIncentives + $preBonus,
                'tds'              => $existingItem ? (float) $existingItem->tds : 0.00,
                'other_deductions' => ($existingItem ? (float) $existingItem->other_deductions : 0.00) + $preDeductions,
            ];
            $paymentStatus = $existingItem ? $existingItem->payment_status : 'pending';
            $paymentRemarks = $existingItem ? $existingItem->payment_remarks : null;

            $item = PayrollEngine::calculatePayslipItem(
                (array) $st,
                $calendarDays,
                $paidDays,
                $lopDays,
                $adj
            );

            // Upsert into payroll_items
            $sql = "INSERT INTO payroll_items (
                        payroll_run_id, employee_id, employment_type, calendar_days, paid_days, lop_days,
                        monthly_ctc, basic, hra, special_allowance, other_allowance, gross_income,
                        arrears, reimbursements, bonus_incentive, gross_earnings,
                        pf_employee, esi_employee, professional_tax, tds, lop_deduction, other_deductions, total_deductions,
                        pf_employer, esi_employer, gratuity, total_employer_cost,
                        net_salary, net_salary_in_words,
                        bank_account_number, bank_name, bank_ifsc, account_holder_name,
                        pan_number, uan_number, pf_number, payment_status, payment_remarks
                    ) VALUES (
                        :run_id, :emp_id, :emp_type, :cal_days, :paid_days, :lop_days,
                        :ctc, :basic, :hra, :special, :other, :gross_inc,
                        :arrears, :reimb, :bonus, :gross_earn,
                        :pf_ee, :esi_ee, :pt, :tds, :lop_ded, :other_ded, :tot_ded,
                        :pf_er, :esi_er, :grat, :er_cost,
                        :net_sal, :net_words,
                        :bank_acc, :bank_name, :bank_ifsc, :holder,
                        :pan, :uan, :pf_num, :status, :remarks
                    ) ON DUPLICATE KEY UPDATE
                        employment_type = VALUES(employment_type),
                        calendar_days = VALUES(calendar_days),
                        paid_days = VALUES(paid_days),
                        lop_days = VALUES(lop_days),
                        monthly_ctc = VALUES(monthly_ctc),
                        basic = VALUES(basic),
                        hra = VALUES(hra),
                        special_allowance = VALUES(special_allowance),
                        other_allowance = VALUES(other_allowance),
                        gross_income = VALUES(gross_income),
                        arrears = VALUES(arrears),
                        reimbursements = VALUES(reimbursements),
                        bonus_incentive = VALUES(bonus_incentive),
                        gross_earnings = VALUES(gross_earnings),
                        pf_employee = VALUES(pf_employee),
                        esi_employee = VALUES(esi_employee),
                        professional_tax = VALUES(professional_tax),
                        tds = VALUES(tds),
                        lop_deduction = VALUES(lop_deduction),
                        other_deductions = VALUES(other_deductions),
                        total_deductions = VALUES(total_deductions),
                        pf_employer = VALUES(pf_employer),
                        esi_employer = VALUES(esi_employer),
                        gratuity = VALUES(gratuity),
                        total_employer_cost = VALUES(total_employer_cost),
                        net_salary = VALUES(net_salary),
                        net_salary_in_words = VALUES(net_salary_in_words),
                        bank_account_number = VALUES(bank_account_number),
                        bank_name = VALUES(bank_name),
                        bank_ifsc = VALUES(bank_ifsc),
                        account_holder_name = VALUES(account_holder_name),
                        pan_number = VALUES(pan_number),
                        uan_number = VALUES(uan_number),
                        pf_number = VALUES(pf_number),
                        updated_at = CURRENT_TIMESTAMP";

            $this->query($sql);
            $this->bind(':run_id', $runId);
            $this->bind(':emp_id', $empId);
            $this->bind(':emp_type', $item['employment_type']);
            $this->bind(':cal_days', $item['calendar_days']);
            $this->bind(':paid_days', $item['paid_days']);
            $this->bind(':lop_days', $item['lop_days']);
            $this->bind(':ctc', $item['monthly_ctc']);
            $this->bind(':basic', $item['basic']);
            $this->bind(':hra', $item['hra']);
            $this->bind(':special', $item['special_allowance']);
            $this->bind(':other', $item['other_allowance']);
            $this->bind(':gross_inc', $item['gross_income']);
            $this->bind(':arrears', $item['arrears']);
            $this->bind(':reimb', $item['reimbursements']);
            $this->bind(':bonus', $item['bonus_incentive']);
            $this->bind(':gross_earn', $item['gross_earnings']);
            $this->bind(':pf_ee', $item['pf_employee']);
            $this->bind(':esi_ee', $item['esi_employee']);
            $this->bind(':pt', $item['professional_tax']);
            $this->bind(':tds', $item['tds']);
            $this->bind(':lop_ded', $item['lop_deduction']);
            $this->bind(':other_ded', $item['other_deductions']);
            $this->bind(':tot_ded', $item['total_deductions']);
            $this->bind(':pf_er', $item['pf_employer']);
            $this->bind(':esi_er', $item['esi_employer']);
            $this->bind(':grat', $item['gratuity']);
            $this->bind(':er_cost', $item['total_employer_cost']);
            $this->bind(':net_sal', $item['net_salary']);
            $this->bind(':net_words', $item['net_salary_in_words']);
            $this->bind(':bank_acc', $st->bank_account_number);
            $this->bind(':bank_name', $st->bank_name);
            $this->bind(':bank_ifsc', $st->bank_ifsc);
            $this->bind(':holder', $st->account_holder_name ?: $st->employee_name);
            $this->bind(':pan', $st->pan_number);
            $this->bind(':uan', $st->uan_number);
            $this->bind(':pf_num', $st->pf_number);
            $this->bind(':status', $paymentStatus);
            $this->bind(':remarks', $paymentRemarks);

            $this->execute();
            $processedCount++;

            $totalGross += $item['gross_earnings'];
            $totalDeductions += $item['total_deductions'];
            $totalNet += $item['net_salary'];
            $totalEmployerCost += $item['total_employer_cost'];
        }

        // 3. Update summary totals on the payroll run
        $this->query("UPDATE payroll_runs
                      SET total_employees = :count,
                          total_gross_pay = :gross,
                          total_deductions = :ded,
                          total_net_pay = :net,
                          total_employer_cost = :cost,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE payroll_run_id = :id");
        $this->bind(':count', $processedCount);
        $this->bind(':gross', $totalGross);
        $this->bind(':ded', $totalDeductions);
        $this->bind(':net', $totalNet);
        $this->bind(':cost', $totalEmployerCost);
        $this->bind(':id', $runId);
        $this->execute();

        return [
            'success' => true,
            'count'   => $processedCount,
            'message' => "Successfully calculated payroll for {$processedCount} employees."
        ];
    }

    /**
     * Get all itemized payslips for a given run with employee information.
     *
     * @param int   $runId
     * @param array $filters (employment_type, payment_status, search)
     * @return array
     */
    /**
     * Get all itemized payslips for a given run with employee information.
     *
     * @param int   $runId
     * @param array $filters (employment_type, payment_status, eligible, search)
     * @return array
     */
    public function getPayrollItems(int $runId, array $filters = []): array {
        $sql = "SELECT pi.*,
                       e.employee_code, e.department, e.job_title as designation, e.branch_id,
                       u.user_id, u.name as employee_name, u.email as employee_email,
                       t.name as team_name
                FROM payroll_items pi
                JOIN employees e ON pi.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE pi.payroll_run_id = :run_id";

        $params = [':run_id' => $runId];

        if (!empty($filters['employment_type'])) {
            $sql .= " AND pi.employment_type = :emp_type";
            $params[':emp_type'] = $filters['employment_type'];
        }

        if (!empty($filters['payment_status'])) {
            $sql .= " AND pi.payment_status = :p_status";
            $params[':p_status'] = $filters['payment_status'];
        }

        if (isset($filters['eligible']) && $filters['eligible'] !== '') {
            $sql .= " AND pi.is_eligible_payslip = :eligible";
            $params[':eligible'] = (int) $filters['eligible'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR e.employee_code LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= " ORDER BY pi.is_eligible_payslip DESC, u.name ASC";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet();
    }

    /**
     * Get a single itemized payslip by item ID.
     *
     * @param int $itemId
     * @return object|null
     */
    public function getItemById(int $itemId) {
        $sql = "SELECT pi.*,
                       pr.pay_month, pr.title as run_title, pr.pay_date, pr.status as run_status,
                       e.employee_code, e.department, e.job_title as designation, e.branch_id, e.hire_date as doj,
                       u.user_id, u.name as employee_name, u.email as employee_email,
                       t.name as team_name
                FROM payroll_items pi
                JOIN payroll_runs pr ON pi.payroll_run_id = pr.payroll_run_id
                JOIN employees e ON pi.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE pi.item_id = :id
                LIMIT 1";
        $this->query($sql);
        $this->bind(':id', $itemId);
        return $this->single() ?: null;
    }

    /**
     * Get payslips available to a specific user (for employee self-service).
     * Only displays payslips that HR chose as eligible and are generated/published.
     *
     * @param int $userId
     * @return array
     */
    public function getPayslipsForUser(int $userId): array {
        $sql = "SELECT pi.*,
                       pr.pay_month, pr.title as run_title, pr.pay_date, pr.status as run_status,
                       e.employee_code, e.department, e.job_title as designation,
                       u.name as employee_name, u.email as employee_email
                FROM payroll_items pi
                JOIN payroll_runs pr ON pi.payroll_run_id = pr.payroll_run_id
                JOIN employees e ON pi.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                WHERE u.user_id = :uid
                  AND pi.is_eligible_payslip = 1
                  AND pr.status IN ('approved', 'disbursed')
                ORDER BY pr.pay_month DESC";

        $this->query($sql);
        $this->bind(':uid', $userId);
        return $this->resultSet();
    }

    /**
     * Toggle or set an individual payslip item's eligibility (whether HR chosen to receive payslip).
     *
     * @param int      $itemId
     * @param int|null $forceState
     * @return bool
     */
    public function toggleItemEligibility(int $itemId, ?int $forceState = null): bool {
        if ($forceState !== null) {
            $sql = "UPDATE payroll_items SET is_eligible_payslip = :st, updated_at = CURRENT_TIMESTAMP WHERE item_id = :id";
            $this->query($sql);
            $this->bind(':st', $forceState ? 1 : 0);
            $this->bind(':id', $itemId);
            return $this->execute();
        }

        $sql = "UPDATE payroll_items SET is_eligible_payslip = IF(is_eligible_payslip = 1, 0, 1), updated_at = CURRENT_TIMESTAMP WHERE item_id = :id";
        $this->query($sql);
        $this->bind(':id', $itemId);
        return $this->execute();
    }

    /**
     * Batch update eligibility for multiple payslip items.
     *
     * @param int   $runId
     * @param array $itemIds
     * @param int   $eligible (1 or 0)
     * @return bool
     */
    public function batchSetEligibility(int $runId, array $itemIds, int $eligible): bool {
        if (empty($itemIds)) {
            return false;
        }
        $inClause = implode(',', array_map('intval', $itemIds));
        $sql = "UPDATE payroll_items
                SET is_eligible_payslip = :elig, updated_at = CURRENT_TIMESTAMP
                WHERE payroll_run_id = :run_id AND item_id IN ($inClause)";
        $this->query($sql);
        $this->bind(':elig', $eligible ? 1 : 0);
        $this->bind(':run_id', $runId);
        return $this->execute();
    }

    /**
     * Record timestamp when payslip was emailed.
     *
     * @param int $itemId
     * @return bool
     */
    public function recordEmailSent(int $itemId): bool {
        $this->query("UPDATE payroll_items SET email_sent_at = CURRENT_TIMESTAMP WHERE item_id = :id");
        $this->bind(':id', $itemId);
        return $this->execute();
    }

    /**
     * Fetch all items in a run for batch printing or archiving.
     *
     * @param int  $runId
     * @param bool $eligibleOnly
     * @return array
     */
    public function getRunItemsForBatch(int $runId, bool $eligibleOnly = true): array {
        $sql = "SELECT pi.*,
                       pr.pay_month, pr.title as run_title, pr.pay_date, pr.status as run_status,
                       e.employee_code, e.department, e.job_title as designation, e.branch_id, e.hire_date as doj,
                       u.user_id, u.name as employee_name, u.email as employee_email,
                       t.name as team_name
                FROM payroll_items pi
                JOIN payroll_runs pr ON pi.payroll_run_id = pr.payroll_run_id
                JOIN employees e ON pi.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE pi.payroll_run_id = :run_id";

        if ($eligibleOnly) {
            $sql .= " AND pi.is_eligible_payslip = 1";
        }

        $sql .= " ORDER BY u.name ASC";

        $this->query($sql);
        $this->bind(':run_id', $runId);
        return $this->resultSet();
    }

    /**
     * Update variable adjustments on a single payslip item and recalculate totals.
     *
     * @param int   $itemId
     * @param array $d (arrears, reimbursements, bonus_incentive, tds, other_deductions, paid_days, payment_status, payment_remarks)
     * @return bool
     */
    public function updateItemAdjustments(int $itemId, array $d): bool {
        $item = $this->getItemById($itemId);
        if (!$item) {
            return false;
        }

        // Get the base structure
        $this->query("SELECT * FROM salary_structures WHERE employee_id = :emp_id");
        $this->bind(':emp_id', $item->employee_id);
        $structure = $this->single();
        if (!$structure) {
            return false;
        }

        $calendarDays = (int) $item->calendar_days;
        $paidDays = isset($d['paid_days']) ? (float) $d['paid_days'] : (float) $item->paid_days;
        $lopDays = max(0, $calendarDays - $paidDays);

        $adj = [
            'arrears'          => isset($d['arrears']) ? (float) $d['arrears'] : (float) $item->arrears,
            'reimbursements'   => isset($d['reimbursements']) ? (float) $d['reimbursements'] : (float) $item->reimbursements,
            'bonus_incentive'  => isset($d['bonus_incentive']) ? (float) $d['bonus_incentive'] : (float) $item->bonus_incentive,
            'tds'              => isset($d['tds']) ? (float) $d['tds'] : (float) $item->tds,
            'other_deductions' => isset($d['other_deductions']) ? (float) $d['other_deductions'] : (float) $item->other_deductions,
        ];

        $recalc = PayrollEngine::calculatePayslipItem((array)$structure, $calendarDays, $paidDays, $lopDays, $adj);

        $status = $d['payment_status'] ?? $item->payment_status;
        $remarks = $d['payment_remarks'] ?? $item->payment_remarks;

        $sql = "UPDATE payroll_items SET
                    paid_days = :paid_days,
                    lop_days = :lop_days,
                    basic = :basic,
                    hra = :hra,
                    special_allowance = :special,
                    other_allowance = :other,
                    gross_income = :gross_inc,
                    arrears = :arrears,
                    reimbursements = :reimb,
                    bonus_incentive = :bonus,
                    gross_earnings = :gross_earn,
                    pf_employee = :pf_ee,
                    esi_employee = :esi_ee,
                    professional_tax = :pt,
                    tds = :tds,
                    other_deductions = :other_ded,
                    total_deductions = :tot_ded,
                    pf_employer = :pf_er,
                    esi_employer = :esi_er,
                    gratuity = :grat,
                    total_employer_cost = :er_cost,
                    net_salary = :net_sal,
                    net_salary_in_words = :net_words,
                    payment_status = :status,
                    payment_remarks = :remarks,
                    updated_at = CURRENT_TIMESTAMP
                WHERE item_id = :id";

        $this->query($sql);
        $this->bind(':paid_days', $recalc['paid_days']);
        $this->bind(':lop_days', $recalc['lop_days']);
        $this->bind(':basic', $recalc['basic']);
        $this->bind(':hra', $recalc['hra']);
        $this->bind(':special', $recalc['special_allowance']);
        $this->bind(':other', $recalc['other_allowance']);
        $this->bind(':gross_inc', $recalc['gross_income']);
        $this->bind(':arrears', $recalc['arrears']);
        $this->bind(':reimb', $recalc['reimbursements']);
        $this->bind(':bonus', $recalc['bonus_incentive']);
        $this->bind(':gross_earn', $recalc['gross_earnings']);
        $this->bind(':pf_ee', $recalc['pf_employee']);
        $this->bind(':esi_ee', $recalc['esi_employee']);
        $this->bind(':pt', $recalc['professional_tax']);
        $this->bind(':tds', $recalc['tds']);
        $this->bind(':other_ded', $recalc['other_deductions']);
        $this->bind(':tot_ded', $recalc['total_deductions']);
        $this->bind(':pf_er', $recalc['pf_employer']);
        $this->bind(':esi_er', $recalc['esi_employer']);
        $this->bind(':grat', $recalc['gratuity']);
        $this->bind(':er_cost', $recalc['total_employer_cost']);
        $this->bind(':net_sal', $recalc['net_salary']);
        $this->bind(':net_words', $recalc['net_salary_in_words']);
        $this->bind(':status', $status);
        $this->bind(':remarks', $remarks);
        $this->bind(':id', $itemId);
        $res = $this->execute();

        // Refresh run aggregates
        $this->recalculateRunTotals((int)$item->payroll_run_id);
        return $res;
    }

    /**
     * Recalculate summary totals for a run from its items.
     *
     * @param int $runId
     */
    public function recalculateRunTotals(int $runId): void {
        $sql = "UPDATE payroll_runs pr
                JOIN (
                    SELECT payroll_run_id,
                           COUNT(*) as total_emp,
                           SUM(gross_earnings) as sum_gross,
                           SUM(total_deductions) as sum_ded,
                           SUM(net_salary) as sum_net,
                           SUM(total_employer_cost) as sum_cost
                    FROM payroll_items
                    WHERE payroll_run_id = :run_id
                    GROUP BY payroll_run_id
                ) stats ON pr.payroll_run_id = stats.payroll_run_id
                SET pr.total_employees = stats.total_emp,
                    pr.total_gross_pay = stats.sum_gross,
                    pr.total_deductions = stats.sum_ded,
                    pr.total_net_pay = stats.sum_net,
                    pr.total_employer_cost = stats.sum_cost,
                    pr.updated_at = CURRENT_TIMESTAMP
                WHERE pr.payroll_run_id = :run_id2";
        $this->query($sql);
        $this->bind(':run_id', $runId);
        $this->bind(':run_id2', $runId);
        $this->execute();
    }

    /**
     * Submit a draft payroll run for Admin approval.
     */
    public function submitRun(int $runId, int $userId): bool {
        $this->query("UPDATE payroll_runs
                      SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE payroll_run_id = :id AND status = 'draft'");
        $this->bind(':id', $runId);
        return $this->execute();
    }

    /**
     * Approve a submitted payroll run.
     */
    public function approveRun(int $runId, int $adminUserId, string $remarks = ''): bool {
        $this->query("UPDATE payroll_runs
                      SET status = 'approved',
                          approved_by = :uid,
                          approved_at = CURRENT_TIMESTAMP,
                          approval_remarks = :remarks,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE payroll_run_id = :id AND status IN ('draft', 'submitted')");
        $this->bind(':uid', $adminUserId);
        $this->bind(':remarks', $remarks);
        $this->bind(':id', $runId);
        return $this->execute();
    }

    /**
     * Mark a payroll run as disbursed.
     */
    public function disburseRun(int $runId): bool {
        $this->query("UPDATE payroll_runs
                      SET status = 'disbursed', disbursed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE payroll_run_id = :id AND status = 'approved'");
        $this->bind(':id', $runId);
        $res = $this->execute();

        if ($res) {
            // Update non-held items to 'paid'
            $this->query("UPDATE payroll_items
                          SET payment_status = 'paid'
                          WHERE payroll_run_id = :id AND payment_status != 'hold'");
            $this->bind(':id', $runId);
            $this->execute();
        }
        return $res;
    }

    /**
     * High level metrics for the Payroll Dashboard.
     */
    public function getSummaryStats(): array {
        $this->query("SELECT
                        COUNT(DISTINCT pr.payroll_run_id) as total_runs,
                        COALESCE(SUM(pr.total_net_pay), 0) as total_disbursed_ytd,
                        COALESCE(SUM(CASE WHEN pr.status = 'submitted' THEN 1 ELSE 0 END), 0) as pending_approvals,
                        (SELECT COUNT(*) FROM salary_structures WHERE is_active = 1) as active_structures,
                        (SELECT COUNT(*) FROM employees) as total_employees
                      FROM payroll_runs pr");
        return (array) ($this->single() ?: []);
    }
}
