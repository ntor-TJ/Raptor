<?php
/**
 * Raptor CRM — Salary Structure Model
 * -----------------------------------------------------------------------------
 * Manages employee salary components, banking details, and statutory configurations.
 */

class SalaryStructure extends Model {

    /**
     * Get all salary structures with employee profile details.
     *
     * @param array $filters (search, employment_type, team_id, is_active)
     * @return array
     */
    public function getAllStructures(array $filters = []): array {
        $sql = "SELECT ss.*,
                       e.employee_id, e.employee_code, e.department, e.job_title as designation, e.branch_id, e.team_id, e.hire_date as doj,
                       u.user_id, u.name as employee_name, u.email as employee_email, u.status as user_status,
                       t.name as team_name
                FROM employees e
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN salary_structures ss ON e.employee_id = ss.employee_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR e.employee_code LIKE :search OR e.job_title LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['employment_type'])) {
            $sql .= " AND ss.employment_type = :emp_type";
            $params[':emp_type'] = $filters['employment_type'];
        }

        if (!empty($filters['team_id'])) {
            $sql .= " AND e.team_id = :team_id";
            $params[':team_id'] = (int) $filters['team_id'];
        }

        if (isset($filters['has_structure'])) {
            if ($filters['has_structure']) {
                $sql .= " AND ss.structure_id IS NOT NULL";
            } else {
                $sql .= " AND ss.structure_id IS NULL";
            }
        }

        $sql .= " ORDER BY u.name ASC";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet();
    }

    /**
     * Get salary structure for a specific employee.
     *
     * @param int $employeeId
     * @return object|null
     */
    public function getByEmployeeId(int $employeeId) {
        $sql = "SELECT ss.*,
                       e.employee_id, e.employee_code, e.department, e.job_title as designation, e.branch_id, e.team_id, e.hire_date as doj,
                       u.user_id, u.name as employee_name, u.email as employee_email,
                       t.name as team_name
                FROM employees e
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN salary_structures ss ON e.employee_id = ss.employee_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE e.employee_id = :emp_id
                LIMIT 1";
        $this->query($sql);
        $this->bind(':emp_id', $employeeId);
        return $this->single() ?: null;
    }

    /**
     * Get salary structure by structure ID.
     *
     * @param int $structureId
     * @return object|null
     */
    public function getById(int $structureId) {
        $sql = "SELECT ss.*,
                       e.employee_id, e.employee_code, e.department, e.job_title as designation, e.hire_date as doj,
                       u.user_id, u.name as employee_name, u.email as employee_email
                FROM salary_structures ss
                JOIN employees e ON ss.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                WHERE ss.structure_id = :id
                LIMIT 1";
        $this->query($sql);
        $this->bind(':id', $structureId);
        return $this->single() ?: null;
    }

    /**
     * Upsert a salary structure record and log salary revision history.
     *
     * @param array $d
     * @param int|null $userId
     * @return int Structure ID
     */
    public function saveStructure(array $d, ?int $userId = null): int {
        $employeeId = (int) $d['employee_id'];
        $employmentType = $d['employment_type'] ?? 'full_time_pf';
        $monthlyCtc = (float) ($d['monthly_ctc'] ?? 0.00);
        $pfApplicable = !empty($d['pf_applicable']) ? 1 : 0;
        $esiApplicable = !empty($d['esi_applicable']) ? 1 : 0;
        $ptApplicable = !empty($d['pt_applicable']) ? 1 : 0;
        $hourlyRate = (float) ($d['hourly_rate'] ?? 0.00);

        // Fetch existing structure to detect salary revisions
        $existing = $this->getByEmployeeId($employeeId);
        $prevCtc = $existing ? (float)$existing->monthly_ctc : 0.00;

        // Auto-compute breakdown if base_salary/hra not explicitly provided
        if (empty($d['base_salary']) || (float) $d['base_salary'] <= 0) {
            $breakdown = PayrollEngine::breakdownCTC($monthlyCtc, $employmentType, (bool)$pfApplicable, (bool)$esiApplicable);
            $baseSalary = $breakdown['base_salary'];
            $hra = $breakdown['hra'];
            $specialAllowance = $breakdown['special_allowance'];
            $otherAllowance = $breakdown['other_allowance'];
        } else {
            $baseSalary = (float) $d['base_salary'];
            $hra = (float) ($d['hra'] ?? ($baseSalary * 0.50));
            $specialAllowance = (float) ($d['special_allowance'] ?? 1600.00);
            $otherAllowance = (float) ($d['other_allowance'] ?? 0.00);
        }

        $effectiveDate = !empty($d['effective_date']) ? $d['effective_date'] : (!empty($d['effective_from']) ? $d['effective_from'] : date('Y-m-d'));
        $effectivePayMonth = !empty($d['effective_pay_month']) ? $d['effective_pay_month'] : date('Y-m', strtotime($effectiveDate));
        $bankAccount = trim($d['bank_account_number'] ?? '');
        $bankName = trim($d['bank_name'] ?? '');
        $bankIfsc = strtoupper(trim($d['bank_ifsc'] ?? ''));
        $accHolder = trim($d['account_holder_name'] ?? '');
        $pan = strtoupper(trim($d['pan_number'] ?? ''));
        $uan = trim($d['uan_number'] ?? '');
        $pfNum = trim($d['pf_number'] ?? '');
        $isActive = isset($d['is_active']) ? (int) $d['is_active'] : 1;

        $sql = "INSERT INTO salary_structures (
                    employee_id, employment_type, monthly_ctc, base_salary, hra,
                    special_allowance, other_allowance, pf_applicable, esi_applicable, pt_applicable,
                    hourly_rate, bank_account_number, bank_name, bank_ifsc, account_holder_name,
                    pan_number, uan_number, pf_number, effective_from, is_active
                ) VALUES (
                    :emp_id, :emp_type, :ctc, :base, :hra,
                    :special, :other, :pf_app, :esi_app, :pt_app,
                    :hourly, :bank_acc, :bank_name, :bank_ifsc, :holder,
                    :pan, :uan, :pf_num, :eff_date, :active
                ) ON DUPLICATE KEY UPDATE
                    employment_type = VALUES(employment_type),
                    monthly_ctc = VALUES(monthly_ctc),
                    base_salary = VALUES(base_salary),
                    hra = VALUES(hra),
                    special_allowance = VALUES(special_allowance),
                    other_allowance = VALUES(other_allowance),
                    pf_applicable = VALUES(pf_applicable),
                    esi_applicable = VALUES(esi_applicable),
                    pt_applicable = VALUES(pt_applicable),
                    hourly_rate = VALUES(hourly_rate),
                    bank_account_number = VALUES(bank_account_number),
                    bank_name = VALUES(bank_name),
                    bank_ifsc = VALUES(bank_ifsc),
                    account_holder_name = VALUES(account_holder_name),
                    pan_number = VALUES(pan_number),
                    uan_number = VALUES(uan_number),
                    pf_number = VALUES(pf_number),
                    effective_from = VALUES(effective_from),
                    is_active = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP";

        $this->query($sql);
        $this->bind(':emp_id', $employeeId);
        $this->bind(':emp_type', $employmentType);
        $this->bind(':ctc', $monthlyCtc);
        $this->bind(':base', $baseSalary);
        $this->bind(':hra', $hra);
        $this->bind(':special', $specialAllowance);
        $this->bind(':other', $otherAllowance);
        $this->bind(':pf_app', $pfApplicable);
        $this->bind(':esi_app', $esiApplicable);
        $this->bind(':pt_app', $ptApplicable);
        $this->bind(':hourly', $hourlyRate);
        $this->bind(':bank_acc', $bankAccount ?: null);
        $this->bind(':bank_name', $bankName ?: null);
        $this->bind(':bank_ifsc', $bankIfsc ?: null);
        $this->bind(':holder', $accHolder ?: null);
        $this->bind(':pan', $pan ?: null);
        $this->bind(':uan', $uan ?: null);
        $this->bind(':pf_num', $pfNum ?: null);
        $this->bind(':eff_date', $effectiveDate);
        $this->bind(':active', $isActive);
        $this->execute();

        $structureId = (int) ($this->lastInsertId() ?: $this->getStructureIdByEmployee($employeeId));

        // Track Revision History if CTC changed or revision reason provided
        $isRevision = ($prevCtc > 0 && abs($prevCtc - $monthlyCtc) > 0.01) || !empty($d['revision_reason']) || !empty($d['is_revision']);
        if ($isRevision) {
            $diffAmount = $monthlyCtc - $prevCtc;
            $requiresArrears = !empty($d['requires_arrears']) ? 1 : 0;
            $reason = !empty($d['revision_reason']) ? trim($d['revision_reason']) : ($prevCtc > 0 ? "Salary adjusted from ₹" . number_format($prevCtc, 2) . " to ₹" . number_format($monthlyCtc, 2) : "Initial Salary Profile Setup");
            $this->recordRevision([
                'employee_id'        => $employeeId,
                'effective_date'     => $effectiveDate,
                'effective_pay_month'=> $effectivePayMonth,
                'previous_ctc'       => $prevCtc,
                'revised_ctc'        => $monthlyCtc,
                'difference_amount'  => $diffAmount,
                'requires_arrears'   => $requiresArrears,
                'reason'             => $reason,
                'source'             => $d['source'] ?? 'manual_entry',
                'status'             => 'approved',
                'user_id'            => $userId
            ]);
        }

        return $structureId;
    }

    /**
     * Record a salary revision entry in the historical log.
     */
    public function recordRevision(array $r): int {
        $sql = "INSERT INTO salary_revisions (
                    employee_id, effective_date, effective_pay_month,
                    previous_ctc, revised_ctc, difference_amount, requires_arrears,
                    reason, source, status, created_by, approved_by, approved_at
                ) VALUES (
                    :emp_id, :eff_date, :eff_month,
                    :prev_ctc, :rev_ctc, :diff, :arr,
                    :reason, :src, :st, :uid, :uid, NOW()
                )";
        $this->query($sql);
        $this->bind(':emp_id', (int)$r['employee_id']);
        $this->bind(':eff_date', $r['effective_date']);
        $this->bind(':eff_month', $r['effective_pay_month']);
        $this->bind(':prev_ctc', (float)$r['previous_ctc']);
        $this->bind(':rev_ctc', (float)$r['revised_ctc']);
        $this->bind(':diff', (float)$r['difference_amount']);
        $this->bind(':arr', (int)$r['requires_arrears']);
        $this->bind(':reason', $r['reason']);
        $this->bind(':src', $r['source'] ?? 'manual_entry');
        $this->bind(':st', $r['status'] ?? 'approved');
        $this->bind(':uid', !empty($r['user_id']) ? (int)$r['user_id'] : null);
        $this->execute();
        return (int) $this->lastInsertId();
    }

    /**
     * Get revision history for a specific employee.
     */
    public function getRevisionHistory(int $employeeId): array {
        $sql = "SELECT sr.*, u.name as approved_by_name, creator.name as created_by_name
                FROM salary_revisions sr
                LEFT JOIN users u ON sr.approved_by = u.user_id
                LEFT JOIN users creator ON sr.created_by = creator.user_id
                WHERE sr.employee_id = :emp_id
                ORDER BY sr.effective_date DESC, sr.revision_id DESC";
        $this->query($sql);
        $this->bind(':emp_id', $employeeId);
        return $this->resultSet();
    }

    /**
     * Get all revisions grouped by employee_id.
     */
    public function getAllRevisionsGrouped(): array {
        $sql = "SELECT sr.*, u.name as approved_by_name
                FROM salary_revisions sr
                LEFT JOIN users u ON sr.approved_by = u.user_id
                ORDER BY sr.effective_date DESC, sr.revision_id DESC";
        $this->query($sql);
        $rows = $this->resultSet();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int)$r->employee_id][] = $r;
        }
        return $grouped;
    }

    private function getStructureIdByEmployee(int $employeeId): int {
        $this->query("SELECT structure_id FROM salary_structures WHERE employee_id = :id");
        $this->bind(':id', $employeeId);
        $res = $this->single();
        return $res ? (int) $res->structure_id : 0;
    }

    /**
     * Auto-populate realistic demo salary structures for any employee missing one.
     */
    public function autoPopulateDefaultStructures(): int {
        $this->query("SELECT e.employee_id, e.employee_code, e.job_title as designation, u.name, u.role_id, r.role_name
                      FROM employees e
                      JOIN users u ON e.user_id = u.user_id
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN salary_structures ss ON e.employee_id = ss.employee_id
                      WHERE ss.structure_id IS NULL");
        $employees = $this->resultSet();
        $count = 0;

        $banks = [
            ['name' => 'HDFC Bank', 'ifsc' => 'HDFC0001042'],
            ['name' => 'Federal Bank', 'ifsc' => 'FDRL0001555'],
            ['name' => 'State Bank of India', 'ifsc' => 'SBIN0008054'],
            ['name' => 'ICICI Bank', 'ifsc' => 'ICIC0001111'],
            ['name' => 'Union Bank of India', 'ifsc' => 'UBIN0815471'],
        ];

        foreach ($employees as $emp) {
            $role = $emp->role_name;
            $ctc = 35000.00;
            $empType = 'full_time_pf';
            $pfApp = 1;

            if ($role === 'admin') {
                $ctc = 100000.00;
            } elseif ($role === 'manager') {
                $ctc = 75000.00;
            } elseif ($role === 'analyst') {
                $ctc = 55000.00;
            } elseif ($role === 'hr') {
                $ctc = 48000.00;
            } else {
                // Employees: rotate CTC range from 25k to 45k
                $ctc = 25000.00 + (($emp->employee_id % 5) * 5000.00);
                if ($emp->employee_id % 8 === 0) {
                    $empType = 'consultant';
                    $pfApp = 0;
                }
            }

            $b = $banks[$emp->employee_id % count($banks)];
            $accNum = '50100' . str_pad((string)($emp->employee_id + 1000000), 9, '0', STR_PAD_LEFT);
            $pan = 'ABCDE' . str_pad((string)($emp->employee_id + 1000), 4, '0', STR_PAD_LEFT) . 'F';
            $uan = '10' . str_pad((string)($emp->employee_id + 1500000000), 10, '0', STR_PAD_LEFT);
            $pfNum = 'TS/HYD/00' . str_pad((string)$emp->employee_id, 7, '0', STR_PAD_LEFT);

            $this->saveStructure([
                'employee_id'         => $emp->employee_id,
                'employment_type'     => $empType,
                'monthly_ctc'         => $ctc,
                'pf_applicable'       => $pfApp,
                'esi_applicable'      => ($ctc <= 21000.00) ? 1 : 0,
                'pt_applicable'       => 1,
                'bank_account_number' => $accNum,
                'bank_name'           => $b['name'],
                'bank_ifsc'           => $b['ifsc'],
                'account_holder_name' => $emp->name,
                'pan_number'          => $pan,
                'uan_number'          => $uan,
                'pf_number'           => $pfNum,
                'effective_from'      => '2026-01-01',
                'is_active'           => 1
            ]);
            $count++;
        }
        return $count;
    }
}
