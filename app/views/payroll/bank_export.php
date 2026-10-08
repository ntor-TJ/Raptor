<div class="container-fluid p-0">
    <!-- Header / Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=payroll/index" class="text-decoration-none">Payroll Runs</a></li>
                    <li class="breadcrumb-item"><a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>" class="text-decoration-none"><?php echo htmlspecialchars($run->pay_month); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bank Disbursement</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa-solid fa-building-columns text-primary me-2"></i>Bank Disbursement Batch &bull; <?php echo htmlspecialchars($run->pay_month); ?>
            </h1>
            <p class="text-muted small mb-0">Direct NEFT/RTGS bank payment transfer register for <?php echo count($items); ?> employees.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="index.php?route=payroll/run/<?php echo (int)$run->payroll_run_id; ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Register
            </a>
            <a href="index.php?route=payroll/bankExportCsv/<?php echo (int)$run->payroll_run_id; ?>" class="btn btn-success">
                <i class="fa-solid fa-file-csv me-1"></i> Download Bank CSV
            </a>
        </div>
    </div>

    <!-- Summary Box -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-4 border-end">
                <div class="text-muted small fw-bold text-uppercase">Total Batch Payout</div>
                <div class="h3 fw-bold text-success mb-0">₹<?php echo number_format($run->total_net_pay, 2); ?></div>
            </div>
            <div class="col-md-4 border-end">
                <div class="text-muted small fw-bold text-uppercase">Total Beneficiaries</div>
                <div class="h3 fw-bold text-dark mb-0"><?php echo count($items); ?> Accounts</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small fw-bold text-uppercase">Scheduled Transfer Date</div>
                <div class="h3 fw-bold text-primary mb-0"><?php echo date('d M Y', strtotime($run->pay_date)); ?></div>
            </div>
        </div>
    </div>

    <!-- Bank Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 font-monospace" style="font-size: 0.85rem;">
                <thead class="table-light text-secondary text-uppercase fw-semibold font-sans-serif" style="font-family: inherit;">
                    <tr>
                        <th class="ps-4">S.No</th>
                        <th>Emp ID</th>
                        <th>Account Holder Name</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                        <th>IFSC Code</th>
                        <th class="text-end">Net Payable (₹)</th>
                        <th>Status</th>
                        <th class="pe-4">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sno = 1; foreach ($items as $it): ?>
                    <tr class="<?php echo ($it->payment_status === 'hold') ? 'table-warning' : ''; ?>">
                        <td class="ps-4 text-muted"><?php echo $sno++; ?></td>
                        <td><strong><?php echo htmlspecialchars($it->employee_code); ?></strong></td>
                        <td class="font-sans-serif">
                            <strong><?php echo htmlspecialchars($it->account_holder_name ?: $it->employee_name); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($it->bank_name ?: 'HDFC Bank'); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($it->bank_account_number ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($it->bank_ifsc ?: '-'); ?></td>
                        <td class="text-end fw-bold text-success font-monospace fs-6">
                            ₹<?php echo number_format($it->net_salary, 2); ?>
                        </td>
                        <td>
                            <?php
                            $stClass = 'bg-secondary text-secondary';
                            if ($it->payment_status === 'paid') $stClass = 'bg-success bg-opacity-10 text-success';
                            elseif ($it->payment_status === 'hold') $stClass = 'bg-danger bg-opacity-10 text-danger border border-danger';
                            elseif ($it->payment_status === 'processed') $stClass = 'bg-info bg-opacity-10 text-info';
                            elseif ($it->payment_status === 'pending') $stClass = 'bg-warning bg-opacity-10 text-warning';
                            ?>
                            <span class="badge <?php echo $stClass; ?> rounded-pill px-2 py-1 text-uppercase" style="font-size: 0.68rem;">
                                <?php echo htmlspecialchars($it->payment_status); ?>
                            </span>
                        </td>
                        <td class="pe-4 text-muted small font-sans-serif">
                            <?php echo htmlspecialchars($it->payment_remarks ?: 'Salary ' . $run->pay_month); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
