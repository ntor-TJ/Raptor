<?php
/**
 * Raptor CRM — Payroll Calculation Engine
 * -----------------------------------------------------------------------------
 * Encapsulates statutory formulas, pro-rata attendance calculations,
 * and Indian currency Number-to-Words formatting based on standard payroll rules.
 */

class PayrollEngine {

    /**
     * Break down a monthly CTC into its statutory and allowance components.
     *
     * @param float  $monthlyCTC
     * @param string $employmentType ('full_time_pf', 'full_time_no_pf', 'consultant', 'trainer_hourly', 'bench')
     * @param bool   $pfApplicable
     * @param bool   $esiApplicable
     * @return array
     */
    public static function breakdownCTC(
        float $monthlyCTC,
        string $employmentType = 'full_time_pf',
        bool $pfApplicable = true,
        bool $esiApplicable = false
    ): array {
        $monthlyCTC = max(0, round($monthlyCTC, 2));

        if ($employmentType === 'trainer_hourly') {
            return [
                'monthly_ctc'       => $monthlyCTC,
                'base_salary'       => 0.00,
                'hra'               => 0.00,
                'special_allowance' => 0.00,
                'other_allowance'   => 0.00,
                'gross_income'      => $monthlyCTC,
                'pf_employee'       => 0.00,
                'pf_employer'       => 0.00,
                'esi_employee'      => 0.00,
                'esi_employer'      => 0.00,
                'gratuity'          => 0.00,
                'professional_tax'  => 0.00,
                'net_pay'           => $monthlyCTC,
            ];
        }

        // Basic is ~50% of CTC
        $baseSalary = round($monthlyCTC * 0.50, 2);
        // HRA is ~50% of Basic
        $hra = round($baseSalary * 0.50, 2);
        // Standard Special Allowance
        $specialAllowance = 1600.00;

        // Statutory contributions
        $pfEmployee = 0.00;
        $pfEmployer = 0.00;
        $gratuity = 0.00;
        $esiEmployee = 0.00;
        $esiEmployer = 0.00;

        if ($pfApplicable && in_array($employmentType, ['full_time_pf', 'bench'], true)) {
            // PF 12% capped at ₹1,800
            $pfCalc = round($baseSalary * 0.12, 2);
            $pfEmployee = min(1800.00, $pfCalc);
            $pfEmployer = $pfEmployee;
            // Gratuity: 4.81% of Basic
            $gratuity = round($baseSalary * 0.0481, 2);
        }

        // Gross Income calculation:
        // Full-time with PF: Gross = CTC - Employer PF - Gratuity
        // Consultants / No PF: Gross = CTC
        if ($pfApplicable && in_array($employmentType, ['full_time_pf', 'bench'], true)) {
            $grossIncome = round($monthlyCTC - $pfEmployer - $gratuity, 2);
        } else {
            $grossIncome = $monthlyCTC;
        }

        if ($esiApplicable && $grossIncome <= 21000.00) {
            $esiEmployee = round($grossIncome * 0.0075, 2);
            $esiEmployer = round($grossIncome * 0.0325, 2);
        }

        // Other / Supplementary Allowance is the balancing figure
        $otherAllowance = max(0, round($grossIncome - $baseSalary - $hra - $specialAllowance, 2));

        // Standard Professional Tax slab (₹200 default, ₹150 for <= 15000)
        $pt = ($grossIncome > 15000.00) ? 200.00 : 150.00;
        if ($grossIncome <= 0) {
            $pt = 0.00;
        }

        $totalDeductions = $pfEmployee + $esiEmployee + $pt;
        $netPay = max(0, round($grossIncome - $totalDeductions, 2));

        return [
            'monthly_ctc'       => $monthlyCTC,
            'base_salary'       => $baseSalary,
            'hra'               => $hra,
            'special_allowance' => $specialAllowance,
            'other_allowance'   => $otherAllowance,
            'gross_income'      => $grossIncome,
            'pf_employee'       => $pfEmployee,
            'pf_employer'       => $pfEmployer,
            'esi_employee'      => $esiEmployee,
            'esi_employer'      => $esiEmployer,
            'gratuity'          => $gratuity,
            'professional_tax'  => $pt,
            'net_pay'           => $netPay,
        ];
    }

    /**
     * Compute a full itemized payslip given an employee's salary structure,
     * calendar days, attendance/paid days, and monthly adjustments.
     *
     * @param array $structure
     * @param int   $calendarDays
     * @param float $paidDays
     * @param float $lopDays
     * @param array $adj (arrears, reimbursements, bonus_incentive, tds, other_deductions, payment_remarks)
     * @return array
     */
    public static function calculatePayslipItem(
        array $structure,
        int $calendarDays = 30,
        float $paidDays = 30.00,
        float $lopDays = 0.00,
        array $adj = []
    ): array {
        $calendarDays = max(1, $calendarDays);
        $paidDays = max(0, min($calendarDays, $paidDays));
        $lopDays = max(0, $calendarDays - $paidDays);
        $ratio = $paidDays / $calendarDays;

        $empType = $structure['employment_type'] ?? 'full_time_pf';
        $monthlyCtc = (float) ($structure['monthly_ctc'] ?? 0.00);
        $baseSalary = (float) ($structure['base_salary'] ?? 0.00);
        $hra = (float) ($structure['hra'] ?? 0.00);
        $specialAllowance = (float) ($structure['special_allowance'] ?? 1600.00);
        $otherAllowance = (float) ($structure['other_allowance'] ?? 0.00);
        $pfApplicable = !empty($structure['pf_applicable']);
        $esiApplicable = !empty($structure['esi_applicable']);
        $ptApplicable = !empty($structure['pt_applicable']);

        // Pro-rata earnings based on paid days
        $proBasic = round(($baseSalary / $calendarDays) * $paidDays, 2);
        $proHra = round(($hra / $calendarDays) * $paidDays, 2);
        $proSpecial = round(($specialAllowance / $calendarDays) * $paidDays, 2);
        $proOther = round(($otherAllowance / $calendarDays) * $paidDays, 2);
        $grossIncome = round($proBasic + $proHra + $proSpecial + $proOther, 2);

        // Variable additions
        $arrears = max(0, round((float) ($adj['arrears'] ?? 0.00), 2));
        $reimbursements = max(0, round((float) ($adj['reimbursements'] ?? 0.00), 2));
        $bonusIncentive = max(0, round((float) ($adj['bonus_incentive'] ?? 0.00), 2));
        $grossEarnings = round($grossIncome + $arrears + $reimbursements + $bonusIncentive, 2);

        // Deductions
        $pfEmployee = 0.00;
        $pfEmployer = 0.00;
        $gratuity = 0.00;
        $esiEmployee = 0.00;
        $esiEmployer = 0.00;

        if ($pfApplicable && in_array($empType, ['full_time_pf', 'bench'], true)) {
            $pfCalc = round($proBasic * 0.12, 2);
            $pfEmployee = min(1800.00, $pfCalc);
            $pfEmployer = $pfEmployee;
            $gratuity = round($proBasic * 0.0481, 2);
        }

        if ($esiApplicable && $grossEarnings <= 21000.00) {
            $esiEmployee = round($grossEarnings * 0.0075, 2);
            $esiEmployer = round($grossEarnings * 0.0325, 2);
        }

        $pt = 0.00;
        if ($ptApplicable && $grossEarnings > 0) {
            $pt = ($grossEarnings > 15000.00) ? 200.00 : 150.00;
        }

        $tds = max(0, round((float) ($adj['tds'] ?? 0.00), 2));
        $lopDeduction = 0.00; // LOP is already handled via pro-rata calculation
        $otherDeductions = max(0, round((float) ($adj['other_deductions'] ?? 0.00), 2));

        $totalDeductions = round(
            $pfEmployee + $esiEmployee + $pt + $tds + $lopDeduction + $otherDeductions,
            2
        );

        $netSalary = max(0, round($grossEarnings - $totalDeductions, 2));
        $totalEmployerCost = round($grossEarnings + $pfEmployer + $esiEmployer + $gratuity, 2);

        return [
            'employment_type'     => $empType,
            'calendar_days'       => $calendarDays,
            'paid_days'           => $paidDays,
            'lop_days'            => $lopDays,
            'monthly_ctc'         => $monthlyCtc,
            'basic'               => $proBasic,
            'hra'                 => $proHra,
            'special_allowance'   => $proSpecial,
            'other_allowance'     => $proOther,
            'gross_income'        => $grossIncome,
            'arrears'             => $arrears,
            'reimbursements'      => $reimbursements,
            'bonus_incentive'     => $bonusIncentive,
            'gross_earnings'      => $grossEarnings,
            'pf_employee'         => $pfEmployee,
            'esi_employee'        => $esiEmployee,
            'professional_tax'    => $pt,
            'tds'                 => $tds,
            'lop_deduction'       => $lopDeduction,
            'other_deductions'    => $otherDeductions,
            'total_deductions'    => $totalDeductions,
            'pf_employer'         => $pfEmployer,
            'esi_employer'        => $esiEmployer,
            'gratuity'            => $gratuity,
            'total_employer_cost' => $totalEmployerCost,
            'net_salary'          => $netSalary,
            'net_salary_in_words' => self::numberToWords($netSalary),
        ];
    }

    /**
     * Convert an amount into Indian Currency Words.
     * Example: 79800 -> "Seventy Nine Thousand Eight Hundred Rupees Only"
     *
     * @param float|int $number
     * @return string
     */
    public static function numberToWords(float $number): string {
        $number = round($number, 2);
        if ($number <= 0) {
            return 'Zero Rupees Only';
        }

        $units = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        ];

        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        ];

        $twoDigits = function(int $n) use ($units, $tens): string {
            if ($n < 20) {
                return $units[$n];
            }
            $t = (int) ($n / 10);
            $u = $n % 10;
            return trim($tens[$t] . ' ' . $units[$u]);
        };

        $threeDigits = function(int $n) use ($units, $twoDigits): string {
            $h = (int) ($n / 100);
            $rest = $n % 100;
            $res = '';
            if ($h > 0) {
                $res .= $units[$h] . ' Hundred';
            }
            if ($rest > 0) {
                if ($res !== '') {
                    $res .= ' and ';
                }
                $res .= $twoDigits($rest);
            }
            return trim($res);
        };

        $rupees = (int) floor($number);
        $paise = (int) round(($number - $rupees) * 100);

        // Indian Numbering System: Crores, Lakhs, Thousands, Hundreds
        $crore = (int) floor($rupees / 10000000);
        $rupees %= 10000000;
        $lakh = (int) floor($rupees / 100000);
        $rupees %= 100000;
        $thousand = (int) floor($rupees / 1000);
        $hundred = $rupees % 1000;

        $words = [];
        if ($crore > 0) {
            $words[] = $twoDigits($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $words[] = $twoDigits($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $words[] = $twoDigits($thousand) . ' Thousand';
        }
        if ($hundred > 0) {
            $words[] = $threeDigits($hundred);
        }

        $result = implode(' ', array_filter($words));
        if ($result === '') {
            $result = 'Zero';
        }

        $formatted = 'Indian Rupee ' . $result;

        if ($paise > 0) {
            $formatted .= ' and ' . $twoDigits($paise) . ' Paise';
        }

        $formatted .= ' Only';
        return trim(preg_replace('/\s+/', ' ', $formatted));
    }

    public static function numberToWordsIndian(float $number): string {
        return self::numberToWords($number);
    }
}
