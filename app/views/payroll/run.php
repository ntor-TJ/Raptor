<div class="container-fluid p-0">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=payroll/index" class="text-decoration-none">Payroll Runs</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($run->pay_month); ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-1 text-gray-800 fw-bold d-flex align-items-center gap-2">
                <span><?php echo htmlspecialchars($run->title); ?></span>
                <?php
                $badgeClass = 'bg-secondary';
                if ($run->status === 'draft') $badgeClass = 'bg-secondary bg-opacity-10 text-secondary border';
                elseif ($run->status === 'submitted') $badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning';
                elseif ($run->status === 'approved') $badgeClass = 'bg-info bg-opacity-10 text-info border border-info';
                elseif ($run->status === 'disbursed') $badgeClass = 'bg-success bg-opacity-10 text-success border border-success';
                ?>
                <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-1 fs-6 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                    <?php echo htmlspecialchars($run->status); ?>
                </span>
            </h1>
            <p class="text-muted small mb-0">
                Period: <strong><?php echo htmlspecialchars($run->pay_month); ?></strong> (<?php echo (int)$run->calendar_days; ?> Calendar Days) &bull;
                Payout Date: <strong><?php echo date('d M Y', strtotime($run->pay_date)); ?></strong> &bull;
                Created by: <strong><?php echo htmlspecialchars($run->creator_name ?? 'System'); ?></strong>
                <?php if ($run->approved_by): ?>
                &bull; Approved by: <strong><?php echo htmlspecialchars($run->approver_name); ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <!-- Run Action Controls -->
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Download / Print All Payslips -->
            <a href="index.php?route=payroll/printBatch/<?php echo (int)$run->payroll_run_id; ?>" target="_blank" class="btn btn-outline-primary" title="View, download or print all eligible employee payslips">
                <i class="fa-solid fa-file-pdf me-1"></i> Download All Payslips
            </a>

            <!-- Send Batch Emails -->
            <button type="button" class="btn btn-outline-success" onclick="sendBatchEmails(<?php echo (int)$run->payroll_run_id; ?>)" title="Dispatch payslip email to all eligible employees">
                <i class="fa-regular fa-envelope me-1"></i> Send All via Email
            </button>

            <!-- Bank Export -->
            <a href="index.php?route=payroll/bankExport/<?php echo (int)$run->payroll_run_id; ?>" class="btn btn-outline-dark">
                <i class="fa-solid fa-file-export me-1"></i> Bank Transfer Sheet
            </a>

            <?php if (in_array($run->status, ['draft', 'submitted'], true)): ?>
            <a href="index.php?route=payroll/generate/<?php echo (int)$run->payroll_run_id; ?>" class="btn btn-outline-secondary" title="Recalculate pro-rata attendance and statutory numbers">
                <i class="fa-solid fa-rotate me-1"></i> Recalculate
            </a>
            <?php endif; ?>

            <?php if ($run->status === 'draft'): ?>
            <form action="index.php?route=payroll/submitRun/<?php echo (int)$run->payroll_run_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Submit this payroll cycle for Administrator review and approval?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn btn-warning text-dark fw-semibold">
                    <i class="fa-solid fa-paper-plane me-1"></i> Submit for Approval
                </button>
            </form>
            <?php endif; ?>

            <?php if ($run->status === 'submitted' && $_SESSION['user_role'] === 'admin'): ?>
            <button type="button" class="btn btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#approveModal">
                <i class="fa-solid fa-check-double me-1"></i> Approve Payroll
            </button>
            <?php endif; ?>

            <?php if ($run->status === 'approved' && $_SESSION['user_role'] === 'admin'): ?>
            <form action="index.php?route=payroll/disburse/<?php echo (int)$run->payroll_run_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Disburse this payroll run? This will mark all non-held employee items as Paid.');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="fa-solid fa-money-bill-transfer me-1"></i> Mark as Disbursed
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['batch_emails_sent'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        Successfully sent payslip notifications to <strong><?php echo (int)$_GET['batch_emails_sent']; ?></strong> eligible employees.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1">Total Net Payout</div>
                <div class="h3 fw-bold text-success mb-1">₹<?php echo number_format($run->total_net_pay, 2); ?></div>
                <div class="text-muted small">Payable to <?php echo (int)$run->total_employees; ?> employees</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1">Gross Earnings</div>
                <div class="h3 fw-bold text-dark mb-1">₹<?php echo number_format($run->total_gross_pay, 2); ?></div>
                <div class="text-muted small">Inclusive of allowances & bonuses</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1">Total Deductions</div>
                <div class="h3 fw-bold text-danger mb-1">₹<?php echo number_format($run->total_deductions, 2); ?></div>
                <div class="text-muted small">PF, PT, TDS, ESI & LOP</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1">Total Employer Cost</div>
                <div class="h3 fw-bold text-primary mb-1">₹<?php echo number_format($run->total_employer_cost, 2); ?></div>
                <div class="text-muted small">Gross + Employer PF + Gratuity</div>
            </div>
        </div>
    </div>

    <!-- Register Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <!-- Filter & HR Choice Bar -->
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>" class="btn btn-sm <?php echo empty($type_filter) ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        All (<?php echo count($items); ?>)
                    </a>
                    <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>&type=full_time_pf" class="btn btn-sm <?php echo ($type_filter === 'full_time_pf') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Full-Time (PF)
                    </a>
                    <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>&type=consultant" class="btn btn-sm <?php echo ($type_filter === 'consultant') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Consultants
                    </a>
                    <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>&type=bench" class="btn btn-sm <?php echo ($type_filter === 'bench') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Bench
                    </a>
                    <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>&status=hold" class="btn btn-sm <?php echo ($status_filter === 'hold') ? 'btn-danger' : 'btn-light border text-danger'; ?> rounded-pill px-3">
                        On Hold
                    </a>
                </div>

                <!-- Search and Batch Selection Action -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- Batch Eligibility Form Trigger -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-check me-1"></i> HR Selection Options
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="executeBatchAction('include')"><i class="fa-solid fa-check text-success me-2"></i>Include Selected for Payslip</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="executeBatchAction('exclude')"><i class="fa-solid fa-ban text-danger me-2"></i>Exclude Selected from Payslip</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="selectAllItems(true)"><i class="fa-regular fa-square-check me-2"></i>Select All</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="selectAllItems(false)"><i class="fa-regular fa-square me-2"></i>Deselect All</a></li>
                        </ul>
                    </div>

                    <!-- Search box -->
                    <form action="index.php" method="GET" class="d-flex gap-2" style="max-width: 260px;">
                        <input type="hidden" name="route" value="payroll/run/<?php echo (int)$run->payroll_run_id; ?>">
                        <?php if (!empty($type_filter)): ?>
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>">
                        <?php endif; ?>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Search employee..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <form id="batchEligibilityForm" action="index.php?route=payroll/batchEligibility" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="payroll_run_id" value="<?php echo (int)$run->payroll_run_id; ?>">
            <input type="hidden" name="batch_action" id="batchActionInput" value="include">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="payrollRegisterTable">
                    <thead class="table-light text-secondary small text-uppercase fw-semibold">
                        <tr>
                            <th class="ps-3" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="checkAllHead" onclick="toggleAllCheckboxes(this)">
                            </th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Attendance</th>
                            <th>Monthly CTC</th>
                            <th>Gross Earned</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Payslip Eligibility</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                No employees match the selected criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $it): ?>
                        <tr class="<?php echo ($it->payment_status === 'hold') ? 'table-warning bg-opacity-25' : ''; ?> <?php echo empty($it->is_eligible_payslip) ? 'opacity-75 bg-light' : ''; ?>">
                            <td class="ps-3">
                                <input type="checkbox" name="item_ids[]" value="<?php echo (int)$it->item_id; ?>" class="form-check-input item-checkbox">
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($it->employee_name); ?></div>
                                <div class="text-muted small">
                                    <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($it->employee_code); ?></span>
                                    <?php echo htmlspecialchars($it->designation); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $typeLabel = 'Full-Time (PF)';
                                $typeBadge = 'bg-primary-soft text-primary';
                                if ($it->employment_type === 'consultant') {
                                    $typeLabel = 'Consultant';
                                    $typeBadge = 'bg-info-soft text-info';
                                } elseif ($it->employment_type === 'bench') {
                                    $typeLabel = 'Bench';
                                    $typeBadge = 'bg-secondary-soft text-secondary';
                                }
                                ?>
                                <span class="badge <?php echo $typeBadge; ?> rounded-pill px-2 py-1 small"><?php echo $typeLabel; ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo (float)$it->paid_days; ?> / <?php echo (int)$it->calendar_days; ?> Days</div>
                                <?php if ((float)$it->lop_days > 0): ?>
                                <div class="text-danger small fw-semibold">LOP: <?php echo (float)$it->lop_days; ?> days</div>
                                <?php else: ?>
                                <div class="text-success small">Full Attendance</div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold">₹<?php echo number_format($it->monthly_ctc, 2); ?></td>
                            <td>
                                <div class="fw-bold text-dark">₹<?php echo number_format($it->gross_earnings, 2); ?></div>
                                <?php if ((float)$it->bonus_incentive > 0 || (float)$it->arrears > 0): ?>
                                <div class="text-muted small text-success">+Bonus/Arrears</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-danger fw-semibold">
                                <div>₹<?php echo number_format($it->total_deductions, 2); ?></div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    PF: ₹<?php echo number_format($it->pf_employee, 0); ?> | PT: ₹<?php echo number_format($it->professional_tax, 0); ?>
                                </div>
                            </td>
                            <td class="text-success fw-bold fs-6">
                                ₹<?php echo number_format($it->net_salary, 2); ?>
                            </td>
                            <td>
                                <?php if (!empty($it->is_eligible_payslip)): ?>
                                <a href="javascript:void(0)" onclick="toggleEligibility(<?php echo (int)$it->item_id; ?>)" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1 text-decoration-none" title="Click to Exclude this employee from receiving payslip">
                                    <i class="fa-solid fa-circle-check me-1"></i> Eligible
                                </a>
                                <?php else: ?>
                                <a href="javascript:void(0)" onclick="toggleEligibility(<?php echo (int)$it->item_id; ?>)" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-1 text-decoration-none" title="Click to Include this employee to receive payslip">
                                    <i class="fa-solid fa-circle-xmark me-1"></i> Excluded
                                </a>
                                <?php endif; ?>

                                <?php if (!empty($it->email_sent_at)): ?>
                                <div class="text-muted" style="font-size: 0.68rem;" title="Emailed on <?php echo date('d M Y, h:i A', strtotime($it->email_sent_at)); ?>">
                                    <i class="fa-solid fa-envelope-circle-check text-success"></i> Emailed
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $stClass = 'bg-secondary text-secondary';
                                if ($it->payment_status === 'paid') $stClass = 'bg-success bg-opacity-10 text-success';
                                elseif ($it->payment_status === 'hold') $stClass = 'bg-danger bg-opacity-10 text-danger border border-danger';
                                elseif ($it->payment_status === 'processed') $stClass = 'bg-info bg-opacity-10 text-info';
                                elseif ($it->payment_status === 'pending') $stClass = 'bg-warning bg-opacity-10 text-warning';
                                ?>
                                <span class="badge <?php echo $stClass; ?> rounded-pill px-2 py-1 text-uppercase" style="font-size: 0.7rem;">
                                    <?php echo htmlspecialchars($it->payment_status); ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <!-- View Payslip -->
                                    <a href="index.php?route=payroll/payslip/<?php echo (int)$it->item_id; ?>" class="btn btn-outline-primary" title="View & Print Payslip">
                                        <i class="fa-solid fa-file-invoice"></i> Payslip
                                    </a>

                                    <!-- Quick Email -->
                                    <button type="button" class="btn btn-outline-secondary" onclick="sendSingleEmail(<?php echo (int)$it->item_id; ?>, '<?php echo addslashes($it->employee_name); ?>')" title="Email Payslip to Employee">
                                        <i class="fa-regular fa-envelope"></i>
                                    </button>

                                    <?php if (in_array($run->status, ['draft', 'submitted'], true)): ?>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustModal_<?php echo (int)$it->item_id; ?>" title="Adjust variable additions & deductions">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Adjust Item Modal -->
                        <div class="modal fade" id="adjustModal_<?php echo (int)$it->item_id; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <form action="index.php?route=payroll/adjustItem/<?php echo (int)$it->item_id; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-sliders text-primary me-2"></i>Adjust Payslip &bull; <?php echo htmlspecialchars($it->employee_name); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body py-3">
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Paid Days (Max <?php echo (int)$it->calendar_days; ?>)</label>
                                                    <input type="number" step="0.5" name="paid_days" class="form-control rounded-3" value="<?php echo (float)$it->paid_days; ?>" min="0" max="<?php echo (int)$it->calendar_days; ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Payment Status</label>
                                                    <select name="payment_status" class="form-select rounded-3">
                                                        <option value="pending" <?php echo ($it->payment_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="hold" <?php echo ($it->payment_status === 'hold') ? 'selected' : ''; ?>>Hold (Block Payout)</option>
                                                        <option value="processed" <?php echo ($it->payment_status === 'processed') ? 'selected' : ''; ?>>Processed</option>
                                                        <option value="paid" <?php echo ($it->payment_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Bonus / Performance Incentive</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" name="bonus_incentive" class="form-control" value="<?php echo (float)$it->bonus_incentive; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Arrears / Retroactive Pay</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" name="arrears" class="form-control" value="<?php echo (float)$it->arrears; ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Tax Deducted at Source (TDS)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" name="tds" class="form-control" value="<?php echo (float)$it->tds; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-secondary">Other Ad-hoc Deductions</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" name="other_deductions" class="form-control" value="<?php echo (float)$it->other_deductions; ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold text-secondary">Disbursement Remarks / Notes</label>
                                                <input type="text" name="payment_remarks" class="form-control rounded-3" value="<?php echo htmlspecialchars($it->payment_remarks ?? ''); ?>" placeholder="e.g., Performance reward, project incentive">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Adjustments</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Admin Approval Modal -->
<?php if ($run->status === 'submitted' && $_SESSION['user_role'] === 'admin'): ?>
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?route=payroll/approve/<?php echo (int)$run->payroll_run_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-success">
                        <i class="fa-solid fa-circle-check me-2"></i>Approve Payroll Run
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-secondary small">
                        You are about to formally approve the payroll disbursement for <strong><?php echo htmlspecialchars($run->title); ?></strong>.
                        Total Net Payout: <strong>₹<?php echo number_format($run->total_net_pay, 2); ?></strong> across <strong><?php echo (int)$run->total_employees; ?></strong> employees.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Approval Remarks</label>
                        <textarea name="approval_remarks" class="form-control rounded-3" rows="3" placeholder="Reviewed attendance, PF & PT compliance. Approved for bank transfer."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function selectAllItems(state) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = state);
    document.getElementById('checkAllHead').checked = state;
}

function executeBatchAction(action) {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one employee from the list.');
        return;
    }

    const actionText = action === 'include' ? 'INCLUDE selected employees for payslip delivery' : 'EXCLUDE selected employees from receiving payslips';
    if (!confirm('Are you sure you want to ' + actionText + ' (' + checked.length + ' employees)?')) {
        return;
    }

    document.getElementById('batchActionInput').value = action;
    document.getElementById('batchEligibilityForm').submit();
}

function toggleEligibility(itemId) {
    fetch('index.php?route=payroll/toggleEligibility/' + itemId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            window.location.reload();
        } else {
            alert('Error updating eligibility: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        window.location.reload();
    });
}

function sendSingleEmail(itemId, empName) {
    if (!confirm('Dispatch payslip email notification to ' + empName + '?')) {
        return;
    }

    fetch('index.php?route=payroll/sendEmail/' + itemId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            alert('✓ ' + data.data.message);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not send email'));
        }
    })
    .catch(err => {
        alert('Network error while dispatching email.');
    });
}

function sendBatchEmails(runId) {
    if (!confirm('Send payslip notifications via email to all eligible employees in this run?')) {
        return;
    }

    fetch('index.php?route=payroll/sendBatchEmails/' + runId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            alert('✓ ' + data.data.message);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not send batch emails'));
        }
    })
    .catch(err => {
        window.location.href = 'index.php?route=payroll/sendBatchEmails/' + runId;
    });
}
</script>
