<?php
/**
 * Raptor CRM — 100% Exact Matching Official Payslip View
 * -----------------------------------------------------------------------------
 * Pixel-perfect implementation matching the Axiora Global Solutions
 * payslip format from payroll_sample_inputs (Zoho Payroll / Employee_Payslips docx/pdf).
 */

require_once dirname(__DIR__, 2) . '/../bin/b64_assets.php';

$logoSrc = !empty($PAYSLIP_LOGO_B64) ? 'data:image/jpeg;base64,' . $PAYSLIP_LOGO_B64 : 'assets/images/payslip_logo.jpeg';
$footerSrc = !empty($PAYSLIP_FOOTER_B64) ? 'data:image/jpeg;base64,' . $PAYSLIP_FOOTER_B64 : 'assets/images/zoho_payroll_footer.jpeg';

$fmt = function($num, $decimals = 2) {
    return '₹' . number_format((float)$num, $decimals, '.', ($decimals > 0 ? '' : ','));
};

$baseCtc = (float)$item->monthly_ctc;
$actualBasic = (float)$item->basic;
$actualHra = (float)$item->hra;
$actualSpecial = (float)$item->special_allowance;
$actualOther = (float)$item->other_allowance;
$actualGross = $actualBasic + $actualHra + $actualSpecial + $actualOther;

$earnedGross = (float)$item->gross_earnings;
$totalDeductions = (float)$item->total_deductions;
$netPayable = (float)$item->net_salary;
$totalBenefits = (float)$item->pf_employer + (float)$item->gratuity;

// Formatting dates
$payDateFormatted = !empty($item->pay_date) ? date('d M Y', strtotime($item->pay_date)) : '01 ' . date('M Y', strtotime('+1 month', strtotime($item->pay_month . '-01')));
$dojFormatted = !empty($item->doj) ? date('d M Y', strtotime($item->doj)) : '05 Mar 2026';
$payPeriodFormatted = date('F Y', strtotime($item->pay_month . '-01'));

// Clean Amount In Words
$words = $item->net_salary_in_words ?: PayrollEngine::numberToWordsIndian($netPayable);
$words = preg_replace('/^Indian\s+Rupee\s+/i', '', $words);
$words = preg_replace('/\s*Rupees\s*/i', ' ', $words);
$words = preg_replace('/\s+/', ' ', trim($words));
?>

<style>
/* ==========================================================================
   100% Exact Match Payslip Print & Screen Styles
   ========================================================================== */
@media print {
    #sidebar, #topbar, .btn-no-print, .breadcrumb, #bottom-nav, footer, .toast-container {
        display: none !important;
    }
    #content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .official-payslip-page {
        max-width: 100% !important;
        padding: 20px 24px !important;
        margin: 0 auto !important;
        box-shadow: none !important;
        border: none !important;
    }
}

.official-payslip-page {
    max-width: 780px;
    margin: 1rem auto 3.5rem auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
    padding: 38px 44px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    color: #000000;
    box-sizing: border-box;
}

/* Header */
.ps-header-block {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 22px;
}
.ps-company-brand {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.ps-company-logo {
    width: 76px;
    height: 76px;
    object-fit: contain;
    border-radius: 4px;
    flex-shrink: 0;
}
.ps-company-name {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 3px;
    letter-spacing: -0.2px;
}
.ps-company-addr {
    font-size: 11.5px;
    color: #475569;
    line-height: 1.45;
    max-width: 390px;
}
.ps-period-block {
    text-align: right;
}
.ps-period-label {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 3px;
}
.ps-period-value {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

/* Outer Rounded Card */
.ps-card-box {
    border: 1.5px solid #0f172a;
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 16px;
    background: #ffffff;
}

/* Summary Box Split */
.ps-summary-grid {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
}
.ps-summary-left {
    flex: 1;
}
.ps-summary-title {
    font-size: 12.5px;
    font-weight: 700;
    letter-spacing: 0.4px;
    color: #000000;
    margin-bottom: 10px;
}
.ps-info-table {
    width: 100%;
    font-size: 11.8px;
    line-height: 1.85;
    border-collapse: collapse;
}
.ps-info-table td {
    padding: 1px 0;
    color: #000000;
    vertical-align: top;
}
.ps-info-table .col-lbl {
    width: 125px;
}
.ps-info-table .col-sep {
    width: 16px;
    text-align: center;
}
.ps-info-table .col-val {
    font-weight: 500;
}

/* Right Top Net Pay Gradient Box */
.ps-summary-right {
    width: 275px;
    flex-shrink: 0;
}
.ps-gradient-net-card {
    border: 1.5px solid #0f172a;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 12px;
}
.ps-gradient-net-top {
    background: linear-gradient(180deg, #c7d2fe 0%, #dbeafe 60%, #eff6ff 100%);
    padding: 12px 14px 8px 14px;
    text-align: center;
}
.ps-big-net-num {
    font-size: 26px;
    font-weight: 700;
    color: #000000;
    letter-spacing: -0.5px;
    line-height: 1.1;
}
.ps-big-net-sub {
    font-size: 11.5px;
    color: #334155;
    margin-top: 2px;
}
.ps-days-row {
    background: #ffffff;
    border-top: 1.5px solid #0f172a;
    padding: 7px 16px;
    font-size: 11.8px;
}
.ps-days-row .day-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3px;
}
.ps-days-row .day-line:last-child {
    margin-bottom: 0;
}

/* Table Card Rows */
.ps-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.8px;
    line-height: 2.1;
}
.ps-data-table th {
    font-weight: 700;
    color: #000000;
    padding: 2px 0;
}
.ps-data-table td {
    padding: 2px 0;
    color: #000000;
}
.ps-data-table .row-total td {
    font-weight: 700;
    color: #000000;
    border-top: 1px solid #cbd5e1;
    padding-top: 4px;
}

/* Benefits & Effective CTC Section */
.ps-benefits-split {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}
.ps-benefits-left {
    flex: 1;
    padding-right: 25px;
}
.ps-ctc-box {
    width: 275px;
    flex-shrink: 0;
    border: 1.5px solid #0f172a;
    border-radius: 12px;
    background: linear-gradient(180deg, #c7d2fe 0%, #dbeafe 60%, #eff6ff 100%);
    padding: 12px 14px;
    text-align: center;
}
.ps-ctc-num {
    font-size: 24px;
    font-weight: 700;
    color: #000000;
    letter-spacing: -0.5px;
    line-height: 1.1;
}
.ps-ctc-sub {
    font-size: 11.5px;
    color: #334155;
    margin-top: 2px;
}

/* Total Net Payable Banner */
.ps-net-banner {
    border: 1.5px solid #0f172a;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: stretch;
    margin-bottom: 8px;
}
.ps-net-banner-left {
    flex: 1;
    background: #ffffff;
    padding: 10px 18px;
}
.ps-net-banner-title {
    font-size: 13px;
    font-weight: 700;
    color: #000000;
    letter-spacing: 0.3px;
}
.ps-net-banner-sub {
    font-size: 11.5px;
    color: #475569;
    margin-top: 1px;
}
.ps-net-banner-right {
    width: 300px;
    background: linear-gradient(180deg, #c7d2fe 0%, #dbeafe 60%, #eff6ff 100%);
    border-left: 1.5px solid #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 18px;
    font-size: 20px;
    font-weight: 700;
    color: #000000;
}

/* Words and Footer */
.ps-words-line-exact {
    text-align: right;
    font-size: 11px;
    color: #334155;
    margin-bottom: 24px;
}
.ps-system-note {
    text-align: center;
    font-size: 9.5px;
    color: #64748b;
    margin-bottom: 26px;
}
.ps-branding-footer {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    font-size: 11.5px;
    color: #475569;
}
.ps-zoho-logo-img {
    height: 24px;
    object-fit: contain;
}
</style>

<div class="container-fluid p-0">
    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 btn-no-print flex-wrap gap-2">
        <div>
            <?php if (!empty($can_manage)): ?>
            <a href="index.php?route=payroll/run/<?php echo (int)$item->payroll_run_id; ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Payroll Register
            </a>
            <?php else: ?>
            <a href="index.php?route=payroll/myPayslips" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to My Payslips
            </a>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <?php if (!empty($item->email_sent_at)): ?>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill small">
                <i class="fa-solid fa-check-circle me-1"></i> Emailed on <?php echo date('d M Y, h:i A', strtotime($item->email_sent_at)); ?>
            </span>
            <?php endif; ?>

            <?php if (!empty($can_manage)): ?>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" onclick="sendPayslipEmail(<?php echo (int)$item->item_id; ?>)">
                <i class="fa-regular fa-envelope me-1"></i> Email to Employee
            </button>
            <?php endif; ?>

            <a href="index.php?route=payroll/downloadPayslip/<?php echo (int)$item->item_id; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-download me-1"></i> Download
            </a>

            <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm" onclick="window.print();">
                <i class="fa-solid fa-print me-1"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <?php if (isset($_GET['email_sent'])): ?>
    <div class="alert alert-success alert-dismissible fade show btn-no-print rounded-3 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        Payslip has been successfully emailed to <strong><?php echo htmlspecialchars($item->employee_email); ?></strong>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- 100% EXACT MATCHING PAYSLIP DOCUMENT -->
    <div class="official-payslip-page" id="payslipDocument">
        <!-- Header -->
        <div class="ps-header-block">
            <div class="ps-company-brand">
                <img src="<?php echo $logoSrc; ?>" class="ps-company-logo" alt="Axiora Logo">
                <div>
                    <div class="ps-company-name">Axiora Global Solutions</div>
                    <div class="ps-company-addr">
                        3rd Floor, Pranava Business Park, beside Harsha Toyota showroom<br>
                        Kothaguda, Kondapur, Hyderabad, Telangana 500084 India.
                    </div>
                </div>
            </div>
            <div class="ps-period-block">
                <div class="ps-period-label">Payslip For the Month</div>
                <div class="ps-period-value"><?php echo htmlspecialchars($payPeriodFormatted); ?></div>
            </div>
        </div>

        <!-- Card 1: EMPLOYEE SUMMARY & NET PAY -->
        <div class="ps-card-box">
            <div class="ps-summary-grid">
                <!-- Left Details -->
                <div class="ps-summary-left">
                    <div class="ps-summary-title">EMPLOYEE SUMMARY</div>
                    <table class="ps-info-table">
                        <tr>
                            <td class="col-lbl">Employee Name</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->employee_name); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Employee ID</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->employee_code); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Pay Period</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($payPeriodFormatted); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Pay Date</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($payDateFormatted); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Date of Joining</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($dojFormatted); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Designation</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->designation); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Location</td>
                            <td class="col-sep">:</td>
                            <td class="col-val">Hyderabad</td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Department</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->department); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Card & Bank -->
                <div class="ps-summary-right">
                    <div class="ps-gradient-net-card">
                        <div class="ps-gradient-net-top">
                            <div class="ps-big-net-num">₹<?php echo number_format($netPayable, 0); ?></div>
                            <div class="ps-big-net-sub">Total Net Pay</div>
                        </div>
                        <div class="ps-days-row">
                            <div class="day-line">
                                <span>Paid Days</span>
                                <span>: &nbsp; <?php echo (int)$item->paid_days; ?></span>
                            </div>
                            <div class="day-line">
                                <span>LOP Days</span>
                                <span>: &nbsp; <?php echo (int)$item->lop_days; ?></span>
                            </div>
                        </div>
                    </div>

                    <table class="ps-info-table">
                        <tr>
                            <td style="width: 110px;">Bank A/C Number</td>
                            <td style="width: 14px; text-align: center;">:</td>
                            <td class="col-val font-monospace"><?php echo htmlspecialchars($item->bank_account_number ?: '99980130961795'); ?></td>
                        </tr>
                        <tr>
                            <td>IFSC Code</td>
                            <td style="text-align: center;">:</td>
                            <td class="col-val font-monospace"><?php echo htmlspecialchars($item->bank_ifsc ?: 'FDRL0001555'); ?></td>
                        </tr>
                        <tr>
                            <td>UAN Number</td>
                            <td style="text-align: center;">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->uan_number ?: '-'); ?></td>
                        </tr>
                        <?php if (!empty($item->pf_number)): ?>
                        <tr>
                            <td>PF Number</td>
                            <td style="text-align: center;">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($item->pf_number); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card 2: COMPENSATION TABLE (EARNINGS, DEDUCTIONS & BENEFITS) -->
        <div class="ps-card-box">
            <table class="ps-data-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 30%;">EARNINGS</th>
                        <th style="text-align: right; width: 18%;">ACTUAL</th>
                        <th style="text-align: right; width: 18%; padding-right: 25px;">AMOUNT</th>
                        <th style="text-align: left; width: 22%; padding-left: 20px;">DEDUCTIONS</th>
                        <th style="text-align: right; width: 12%;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic</td>
                        <td style="text-align: right;">₹<?php echo number_format($actualBasic, 2); ?></td>
                        <td style="text-align: right; padding-right: 25px;">₹<?php echo number_format($item->basic, 2); ?></td>
                        <td style="padding-left: 20px;">EPF Contribution</td>
                        <td style="text-align: right;">₹<?php echo number_format($item->pf_employee, 2); ?></td>
                    </tr>
                    <tr>
                        <td>House Rent Allowance</td>
                        <td style="text-align: right;">₹<?php echo number_format($actualHra, 2); ?></td>
                        <td style="text-align: right; padding-right: 25px;">₹<?php echo number_format($item->hra, 2); ?></td>
                        <td style="padding-left: 20px;">Professional Tax</td>
                        <td style="text-align: right;">₹<?php echo number_format($item->professional_tax, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Special Allowance</td>
                        <td style="text-align: right;">₹<?php echo number_format($actualSpecial, 2); ?></td>
                        <td style="text-align: right; padding-right: 25px;">₹<?php echo number_format($item->special_allowance, 2); ?></td>
                        <td style="padding-left: 20px;"><?php if ((float)$item->tds > 0) echo 'TDS'; ?></td>
                        <td style="text-align: right;"><?php if ((float)$item->tds > 0) echo '₹' . number_format($item->tds, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Other Allowance</td>
                        <td style="text-align: right;">₹<?php echo number_format($actualOther, 2); ?></td>
                        <td style="text-align: right; padding-right: 25px;">₹<?php echo number_format($item->other_allowance, 2); ?></td>
                        <td style="padding-left: 20px;"><?php if ((float)$item->other_deductions > 0) echo 'Other Deductions'; ?></td>
                        <td style="text-align: right;"><?php if ((float)$item->other_deductions > 0) echo '₹' . number_format($item->other_deductions, 2); ?></td>
                    </tr>
                    <tr class="row-total">
                        <td>Gross Earnings</td>
                        <td style="text-align: right;">₹<?php echo number_format($actualGross, 2); ?></td>
                        <td style="text-align: right; padding-right: 25px;">₹<?php echo number_format($earnedGross, 2); ?></td>
                        <td style="padding-left: 20px;">Total Deductions</td>
                        <td style="text-align: right;">₹<?php echo number_format($totalDeductions, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Benefits & CTC Row -->
            <div class="ps-benefits-split">
                <div class="ps-benefits-left">
                    <table class="ps-data-table" style="line-height: 1.9;">
                        <tr>
                            <td style="font-weight: 700; width: 60%;">Employee Benefits</td>
                            <td style="font-weight: 700; width: 40%; text-align: right;">AMOUNT</td>
                        </tr>
                        <tr>
                            <td>Employer EPF Contribution</td>
                            <td style="text-align: right;">₹<?php echo number_format($item->pf_employer, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Gratuity</td>
                            <td style="text-align: right;">₹<?php echo number_format($item->gratuity, 2); ?></td>
                        </tr>
                        <tr class="row-total">
                            <td>Gross Earnings</td>
                            <td style="text-align: right;">₹<?php echo number_format($totalBenefits, 2); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right CTC Card -->
                <div class="ps-ctc-box">
                    <div class="ps-ctc-num">₹<?php echo number_format($baseCtc, 0); ?></div>
                    <div class="ps-ctc-sub">Effective Monthly CTC</div>
                </div>
            </div>
        </div>

        <!-- Card 3: TOTAL NET PAYABLE Split Banner -->
        <div class="ps-net-banner">
            <div class="ps-net-banner-left">
                <div class="ps-net-banner-title">TOTAL NET PAYABLE</div>
                <div class="ps-net-banner-sub">Gross Earnings – Total Deductions</div>
            </div>
            <div class="ps-net-banner-right">
                ₹<?php echo number_format($netPayable, 2); ?>
            </div>
        </div>

        <!-- Amount In Words Line -->
        <div class="ps-words-line-exact">
            Amount In Words: <strong>Indian Rupee <?php echo htmlspecialchars($words); ?> Only</strong>
        </div>

        <!-- System Generated Notice -->
        <div class="ps-system-note">
            -- This is a system-generated document. --
        </div>

        <!-- Zoho Payroll Powered By Footer Branding -->
        <div class="ps-branding-footer">
            <span>Powered by</span>
            <img src="<?php echo $footerSrc; ?>" class="ps-zoho-logo-img" alt="Zoho Payroll">
            <span style="color: #cbd5e1;">|</span>
            <span>Simplify payroll and compliance. Visit <a href="https://zoho.com/payroll" target="_blank" style="color: #2563eb; text-decoration: none;">zoho.com/payroll</a></span>
        </div>
    </div>
</div>

<script>
function sendPayslipEmail(itemId) {
    if (!confirm('Send payslip via email to <?php echo addslashes($item->employee_name); ?> (<?php echo addslashes($item->employee_email); ?>)?')) {
        return;
    }

    fetch('index.php?route=payroll/sendEmail/' + itemId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            alert('✓ Payslip successfully dispatched to ' + data.data.recipient);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not send email'));
        }
    })
    .catch(err => {
        alert('Network error while dispatching email.');
    });
}
</script>
