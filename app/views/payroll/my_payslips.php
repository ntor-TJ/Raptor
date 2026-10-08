<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa-solid fa-money-bill-wave text-primary me-2"></i>My Payslips
            </h1>
            <p class="text-muted small mb-0">View and download your monthly salary slips, tax breakdown, and attendance records.</p>
        </div>
    </div>

    <!-- Payslips List Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="mb-0 fw-bold text-gray-800">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Available Salary Slips
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase fw-semibold">
                    <tr>
                        <th class="ps-4">Pay Period</th>
                        <th>Pay Date</th>
                        <th>Days Paid</th>
                        <th>Gross Earnings</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payslips)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-file-circle-xmark fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                            No approved payslips available for your account yet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($payslips as $ps): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($ps->run_title ?: $ps->pay_month); ?></div>
                            <div class="text-muted small">Month: <?php echo htmlspecialchars($ps->pay_month); ?></div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($ps->pay_date)); ?></td>
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1"><?php echo (float)$ps->paid_days; ?> / <?php echo (int)$ps->calendar_days; ?> Days</span>
                        </td>
                        <td class="fw-semibold">₹<?php echo number_format($ps->gross_earnings, 2); ?></td>
                        <td class="text-danger fw-semibold">₹<?php echo number_format($ps->total_deductions, 2); ?></td>
                        <td class="text-success fw-bold fs-6">₹<?php echo number_format($ps->net_salary, 2); ?></td>
                        <td>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 text-uppercase" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($ps->payment_status); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php?route=payroll/downloadPayslip/<?php echo (int)$ps->item_id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm" title="Download Payslip HTML">
                                    <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                                <a href="index.php?route=payroll/payslip/<?php echo (int)$ps->item_id; ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                    <i class="fa-solid fa-eye me-1"></i> View / Print
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
