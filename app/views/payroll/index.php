<div class="container-fluid p-0">
    <!-- Header / Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa-solid fa-calculator text-primary me-2"></i>Payroll Management
            </h1>
            <p class="text-muted small mb-0">Manage monthly salary runs, attendance-driven pro-rata payouts, statutory compliance, and banking disbursements.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=payroll/structures" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fa-solid fa-id-card-clip me-1"></i> Salary Structures
            </a>
            <a href="index.php?route=payroll/readiness" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-shield-halved me-1"></i> Start Payroll Validation & Run
            </a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Disbursed (YTD)</div>
                        <div class="h4 mb-0 fw-bold text-success">₹<?php echo number_format($stats['total_disbursed_ytd'] ?? 0, 2); ?></div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-money-bill-trend-up fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Payroll Cycles</div>
                        <div class="h4 mb-0 fw-bold text-primary"><?php echo (int) ($stats['total_runs'] ?? 0); ?> Runs</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Active Salary Profiles</div>
                        <div class="h4 mb-0 fw-bold text-info"><?php echo (int) ($stats['active_structures'] ?? 0); ?> / <?php echo (int) ($stats['total_employees'] ?? 0); ?></div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                        <i class="fa-solid fa-users fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Pending Approvals</div>
                        <div class="h4 mb-0 fw-bold <?php echo ($stats['pending_approvals'] > 0) ? 'text-warning' : 'text-secondary'; ?>">
                            <?php echo (int) ($stats['pending_approvals'] ?? 0); ?> Submitted
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="fa-solid fa-file-signature fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Payroll Runs Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-gray-800">
                <i class="fa-solid fa-list-ul text-primary me-2"></i>Monthly Payroll Register Runs
            </h5>
            <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill fw-semibold">
                <?php echo count($runs); ?> Cycles
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase fw-semibold">
                    <tr>
                        <th class="ps-4">Pay Period</th>
                        <th>Pay Date</th>
                        <th>Employees</th>
                        <th>Total Gross</th>
                        <th>Deductions</th>
                        <th>Net Payout</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($runs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                            No payroll runs created yet. Click <a href="index.php?route=payroll/readiness" class="fw-bold text-primary">Start Payroll Validation & Run</a> above to launch the readiness engine.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($runs as $r): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($r->title); ?></div>
                            <div class="text-muted small">Month: <?php echo htmlspecialchars($r->pay_month); ?> (<?php echo (int)$r->calendar_days; ?> Days)</div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo date('d M Y', strtotime($r->pay_date)); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1"><?php echo (int) $r->total_employees; ?> Staff</span>
                        </td>
                        <td class="fw-semibold">₹<?php echo number_format($r->total_gross_pay, 2); ?></td>
                        <td class="text-danger fw-semibold">₹<?php echo number_format($r->total_deductions, 2); ?></td>
                        <td class="text-success fw-bold">₹<?php echo number_format($r->total_net_pay, 2); ?></td>
                        <td>
                            <?php
                            $badgeClass = 'bg-secondary';
                            if ($r->status === 'draft') $badgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                            elseif ($r->status === 'submitted') $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                            elseif ($r->status === 'approved') $badgeClass = 'bg-info bg-opacity-10 text-info';
                            elseif ($r->status === 'disbursed') $badgeClass = 'bg-success bg-opacity-10 text-success';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?> px-3 py-1 rounded-pill text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                <?php echo htmlspecialchars($r->status); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="index.php?route=payroll/run/<?php echo (int)$r->payroll_run_id; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                View Register <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: New Payroll Run -->
<div class="modal fade" id="newRunModal" tabindex="-1" aria-labelledby="newRunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?route=payroll/create" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="newRunModalLabel">
                        <i class="fa-solid fa-calculator text-primary me-2"></i>Create New Payroll Run
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Pay Month (YYYY-MM)</label>
                        <input type="month" name="pay_month" class="form-control rounded-3" value="<?php echo date('Y-m'); ?>" required>
                        <div class="form-text">Select the payroll period to calculate attendance and pro-rata salary.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Calendar Days</label>
                            <input type="number" name="calendar_days" class="form-control rounded-3" value="<?php echo date('t'); ?>" min="28" max="31" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Payout Date</label>
                            <input type="date" name="pay_date" class="form-control rounded-3" value="<?php echo date('Y-m-01', strtotime('+1 month')); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Custom Title (Optional)</label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. <?php echo date('F Y'); ?> Payroll">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-play me-1"></i> Create & Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
