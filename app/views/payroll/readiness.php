<?php
/**
 * Raptor CRM — Payroll Readiness Control Center View
 * -----------------------------------------------------------------------------
 * 14 Sequential Checkpoints, Pre-processing validation, Exception Resolution,
 * Manual Entry, Bulk CSV Upload, and Mandatory Gated Payroll Run.
 */

$readinessPct = $summary['readiness_score'] ?? 0;
$totalExceptions = $summary['total_exceptions'] ?? 0;
$blockingErrors = $summary['blocking_errors'] ?? 0;
$warningCount = $summary['warning_count'] ?? 0;
$checkpoints = $summary['checkpoints'] ?? [];
$exceptions = $summary['exceptions'] ?? [];
$isReadyToRun = ($blockingErrors === 0 && count($checkpoints) > 0);

$statusBadgeMap = [
    'completed'                 => ['class' => 'bg-success text-white', 'icon' => 'fa-circle-check', 'label' => 'Completed'],
    'completed_with_exceptions' => ['class' => 'bg-warning text-dark', 'icon' => 'fa-triangle-exclamation', 'label' => 'Completed with Exceptions'],
    'action_required'           => ['class' => 'bg-danger text-white', 'icon' => 'fa-circle-exclamation', 'label' => 'Action Required'],
    'in_progress'               => ['class' => 'bg-primary text-white', 'icon' => 'fa-spinner fa-spin', 'label' => 'In Progress'],
    'failed'                    => ['class' => 'bg-danger text-white', 'icon' => 'fa-circle-xmark', 'label' => 'Failed'],
    'not_started'               => ['class' => 'bg-secondary text-white', 'icon' => 'fa-circle-pause', 'label' => 'Not Started']
];
?>

<div class="container-fluid p-0">

    <!-- Flash Alert for Gate Rejections / Upload Messages -->
    <?php if (!empty($_SESSION['payroll_gate_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-ban fa-2x me-3 text-danger"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Payroll Execution Blocked</h5>
                <div><?php echo htmlspecialchars($_SESSION['payroll_gate_error']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['payroll_gate_error']); endif; ?>

    <?php if (!empty($_SESSION['upload_result'])): 
        $uRes = $_SESSION['upload_result'];
    ?>
    <div class="alert <?php echo ($uRes['success'] ? 'alert-success' : 'alert-warning'); ?> alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fa-solid <?php echo ($uRes['success'] ? 'fa-check-circle' : 'fa-triangle-exclamation'); ?> fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1"><?php echo htmlspecialchars($uRes['message'] ?? 'Upload Processed'); ?></h5>
                <div class="small">
                    Total Rows: <strong><?php echo (int)($uRes['total_rows'] ?? 0); ?></strong> |
                    Imported: <span class="text-success fw-bold"><?php echo (int)($uRes['valid_count'] ?? 0); ?></span> |
                    Errors/Duplicates: <span class="text-danger fw-bold"><?php echo (int)($uRes['error_count'] ?? 0); ?></span>
                </div>
                <?php if (!empty($uRes['errors'])): ?>
                <div class="mt-2 text-danger small">
                    <strong>Sample Errors:</strong>
                    <ul class="mb-0 ps-3">
                        <?php foreach (array_slice($uRes['errors'], 0, 3) as $err): ?>
                        <li>Row <?php echo (int)$err['row']; ?> (<?php echo htmlspecialchars($err['employee_code'] ?? ''); ?>): <?php echo htmlspecialchars($err['reason']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['upload_result']); endif; ?>

    <!-- Top Navigation & Payroll Month Bar -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-6 col-md-12">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                            <i class="fa-solid fa-shield-halved fa-2x"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h2 class="h4 mb-0 fw-bold text-dark">Payroll Readiness Control Center</h2>
                                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill small fw-semibold">Pre-Run Engine</span>
                            </div>
                            <p class="text-muted small mb-0 mt-1">
                                Strict validation gateway. All 14 mandatory checkpoints must be cleared before the payroll run unlocks.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-12">
                    <form method="GET" action="index.php" class="d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                        <input type="hidden" name="route" value="payroll/readiness">
                        <div class="input-group" style="max-width: 260px;">
                            <span class="input-group-text bg-light border-end-0 text-muted fw-semibold">
                                <i class="fa-solid fa-calendar-days me-1"></i> Month:
                            </span>
                            <input type="month" name="month" class="form-control border-start-0 fw-bold text-primary" value="<?php echo htmlspecialchars($pay_month); ?>" onchange="this.form.submit()">
                        </div>

                        <button type="submit" name="evaluate" value="1" class="btn btn-outline-primary rounded-pill px-3">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Re-Validate All
                        </button>

                        <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manualEntryModal">
                            <i class="fa-solid fa-plus-circle me-1"></i> Manual Entry
                        </button>

                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Bulk Upload
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Month Metrics & Readiness Score Banner -->
    <div class="row g-3 mb-4">
        <!-- Readiness Gauge Card -->
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase text-secondary fw-bold small">Readiness Completion</span>
                    <span class="badge <?php echo $isReadyToRun ? 'bg-success text-white' : 'bg-warning text-dark'; ?> px-3 py-1 rounded-pill fw-bold">
                        <?php echo $isReadyToRun ? 'READY TO RUN' : ($blockingErrors > 0 ? 'LOCKED (EXCEPTIONS)' : 'IN PROGRESS'); ?>
                    </span>
                </div>
                
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h1 class="display-4 fw-black text-dark mb-0"><?php echo $readinessPct; ?>%</h1>
                    <span class="text-muted fw-semibold">/ 100% Validated</span>
                </div>

                <div class="progress rounded-pill mb-3" style="height: 12px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $readinessPct == 100 ? 'bg-success' : ($readinessPct >= 80 ? 'bg-primary' : 'bg-warning'); ?>"
                         role="progressbar" style="width: <?php echo $readinessPct; ?>%;" aria-valuenow="<?php echo $readinessPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="d-flex justify-content-between text-muted small border-top pt-2">
                    <div><i class="fa-solid fa-list-check text-primary me-1"></i> 14 Checkpoints</div>
                    <div><i class="fa-solid fa-triangle-exclamation <?php echo $blockingErrors > 0 ? 'text-danger' : 'text-success'; ?> me-1"></i> <?php echo $blockingErrors; ?> Blocking</div>
                    <div><i class="fa-solid fa-circle-info text-info me-1"></i> <?php echo $warningCount; ?> Warnings</div>
                </div>
            </div>
        </div>

        <!-- Population Snapshot Cards -->
        <div class="col-xl-8 col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small fw-semibold">Total Active</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle"><i class="fa-solid fa-users"></i></span>
                        </div>
                        <div class="mt-2">
                            <h3 class="fw-bold mb-0 text-dark"><?php echo (int)$total_active; ?></h3>
                            <div class="text-muted small">Eligible for cycle</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small fw-semibold">New Joiners</span>
                            <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle"><i class="fa-solid fa-user-plus"></i></span>
                        </div>
                        <div class="mt-2">
                            <h3 class="fw-bold mb-0 text-success"><?php echo (int)$new_joiners_count; ?></h3>
                            <div class="text-muted small">Pro-rata calculated</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small fw-semibold">Exited / F&F</span>
                            <span class="badge bg-danger bg-opacity-10 text-danger p-2 rounded-circle"><i class="fa-solid fa-user-minus"></i></span>
                        </div>
                        <div class="mt-2">
                            <h3 class="fw-bold mb-0 text-danger"><?php echo (int)$exited_count; ?></h3>
                            <div class="text-muted small">Exit settlements</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small fw-semibold">Prev Month Ref</span>
                            <span class="badge bg-info bg-opacity-10 text-info p-2 rounded-circle"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        </div>
                        <div class="mt-2">
                            <h4 class="fw-bold mb-0 text-dark"><?php echo $prev_run ? '₹' . number_format($prev_run->total_net_pay, 0) : 'N/A'; ?></h4>
                            <div class="text-muted small"><?php echo $prev_month; ?> (<?php echo $prev_run ? (int)$prev_run->total_employees . ' Staff' : 'No cycle'; ?>)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: 14 Mandatory Pre-Payroll Checkpoints Grid -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Mandatory Pre-Payroll Checkpoints (14)</h4>
            <p class="text-muted small mb-0">Every checkpoint validates specific compliance, master, attendance, or variable data items.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill small">
                <i class="fa-solid fa-check-circle me-1"></i> <?php echo count(array_filter($checkpoints, fn($c) => $c->status === 'completed')); ?> Completed
            </span>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill small">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $blockingErrors; ?> Blocking Exceptions
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($checkpoints_def as $code => $def): 
            $cp = $checkpoints[$code] ?? null;
            $status = $cp ? $cp->status : 'not_started';
            $badge = $statusBadgeMap[$status] ?? $statusBadgeMap['not_started'];
            $records = $cp ? (int)$cp->records_count : 0;
            $excCount = $cp ? (int)$cp->exceptions_count : 0;
            $hasBlocking = ($cp && (int)$cp->blocking_errors_count > 0);
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100 p-3 position-relative overflow-hidden checkpoint-card <?php echo $hasBlocking ? 'border-start border-4 border-danger' : ($status === 'completed' ? 'border-start border-4 border-success' : ''); ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="fw-bold text-dark text-truncate pe-2" title="<?php echo htmlspecialchars($def['name']); ?>">
                        <?php echo htmlspecialchars($def['name']); ?>
                    </div>
                    <span class="badge <?php echo $badge['class']; ?> px-2 py-1 rounded-pill small" style="font-size: 0.7rem;">
                        <i class="fa-solid <?php echo $badge['icon']; ?> me-1"></i> <?php echo $badge['label']; ?>
                    </span>
                </div>

                <p class="text-muted small mb-3 flex-grow-1" style="min-height: 38px; font-size: 0.8rem; line-height: 1.3;">
                    <?php echo htmlspecialchars($def['description']); ?>
                </p>

                <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-2 mb-3 text-secondary small">
                    <div>
                        <span class="fw-bold text-dark"><?php echo $records; ?></span> Records
                    </div>
                    <div>
                        <?php if ($excCount > 0): ?>
                        <span class="badge <?php echo $hasBlocking ? 'bg-danger' : 'bg-warning text-dark'; ?> rounded-pill">
                            <?php echo $excCount; ?> Exceptions <?php if ($hasBlocking): ?>(Blocking)<?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-success"><i class="fa-solid fa-check"></i> 0 Exceptions</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Toolbar for Checkpoint -->
                <div class="d-flex flex-wrap gap-1 pt-1 border-top">
                    <?php if (in_array($code, ['lop', 'incentives', 'deductions', 'bonus', 'arrears', 'salary_revisions'])): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0 text-nowrap" style="font-size: 0.75rem;"
                            onclick="openManualEntry('<?php echo $code; ?>')">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0 text-nowrap" style="font-size: 0.75rem;"
                            onclick="openBulkUpload('<?php echo $code; ?>')">
                        <i class="fa-solid fa-upload"></i> Upload
                    </button>
                    <a href="index.php?route=payroll/downloadTemplate/<?php echo $code; ?>" class="btn btn-sm btn-light rounded-pill px-2 py-0 text-nowrap text-muted" style="font-size: 0.75rem;" title="Download CSV Template">
                        <i class="fa-solid fa-file-csv"></i> Template
                    </a>
                    <?php else: ?>
                    <a href="#exceptionsSection" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0 text-nowrap" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-eye"></i> View Log
                    </a>
                    <?php endif; ?>

                    <?php if ($excCount > 0): ?>
                    <a href="#exceptionsSection" class="btn btn-sm btn-danger rounded-pill px-2 py-0 text-nowrap ms-auto" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-wrench"></i> Fix (<?php echo $excCount; ?>)
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Section: Unresolved Payroll Exceptions & Action Required -->
    <div id="exceptionsSection" class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Exception Queue & Audit Approvals
                </h5>
                <p class="text-muted small mb-0">Review all flagged inconsistencies. Blocking errors must be resolved or authorized by HR before running payroll.</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-semibold">
                    <?php echo $blockingErrors; ?> Blocking
                </span>
                <span class="badge bg-warning-soft text-warning px-3 py-2 rounded-pill fw-semibold">
                    <?php echo $warningCount; ?> Warnings
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase fw-semibold">
                    <tr>
                        <th class="ps-4">Severity</th>
                        <th>Checkpoint</th>
                        <th>Employee</th>
                        <th>Issue Identified</th>
                        <th>Suggested Action</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Resolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exceptions)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-circle-check text-success fa-3x mb-3 d-block opacity-75"></i>
                            <h6 class="fw-bold text-dark">All Checkpoints Passed!</h6>
                            <p class="small text-muted mb-0">No open exceptions or blocking errors detected for <?php echo htmlspecialchars($pay_month); ?>.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($exceptions as $ex): 
                        $isResolved = ((int)$ex->is_resolved === 1);
                        $sevBadge = match($ex->severity) {
                            'blocking' => 'bg-danger text-white',
                            'warning'  => 'bg-warning text-dark',
                            default    => 'bg-info text-white'
                        };
                    ?>
                    <tr class="<?php echo $isResolved ? 'table-light opacity-50' : ($ex->severity === 'blocking' ? 'table-danger bg-opacity-10' : ''); ?>">
                        <td class="ps-4">
                            <span class="badge <?php echo $sevBadge; ?> px-2 py-1 rounded-pill small text-uppercase" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($ex->severity); ?>
                            </span>
                        </td>
                        <td class="fw-semibold text-dark small">
                            <?php echo htmlspecialchars($checkpoints_def[$ex->checkpoint_key]['name'] ?? $ex->checkpoint_key); ?>
                        </td>
                        <td>
                            <?php if (!empty($ex->employee_name)): ?>
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($ex->employee_name); ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($ex->employee_code ?? ''); ?> (ID: <?php echo (int)$ex->employee_id; ?>)</div>
                            <?php else: ?>
                            <span class="text-muted small">System Level</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-dark">
                            <div class="fw-semibold"><?php echo htmlspecialchars($ex->title ?? ''); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($ex->description ?? ''); ?></div>
                        </td>
                        <td class="small text-muted">
                            <?php echo htmlspecialchars($ex->suggested_action ?? 'Review employee profile / adjustment.'); ?>
                        </td>
                        <td>
                            <?php if ($isResolved): ?>
                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded-pill small">
                                <i class="fa-solid fa-check me-1"></i> Resolved
                            </span>
                            <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 rounded-pill small">
                                <i class="fa-solid fa-clock me-1"></i> Pending
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if (!$isResolved): ?>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3"
                                    onclick="openResolveModal(<?php echo (int)$ex->exception_id; ?>, '<?php echo htmlspecialchars(addslashes($ex->title . ' - ' . $ex->description)); ?>', '<?php echo htmlspecialchars(addslashes($ex->employee_name ?? 'System')); ?>')">
                                <i class="fa-solid fa-check-double me-1"></i> Resolve / Override
                            </button>
                            <?php else: ?>
                            <span class="text-muted small" title="<?php echo htmlspecialchars($ex->resolution_reason ?? ''); ?>">
                                <i class="fa-solid fa-circle-check text-success"></i> Fixed
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MANDATORY PAYROLL GATE CONTROL FOOTER -->
    <div class="card border-0 shadow-lg rounded-4 <?php echo $isReadyToRun ? 'bg-gradient text-white' : 'bg-light border-warning'; ?> p-4 mb-4"
         style="<?php echo $isReadyToRun ? 'background: linear-gradient(135deg, #1e3a8a 0%, #0d9488 100%);' : ''; ?>">
        <div class="row align-items-center g-3">
            <div class="col-lg-7 col-md-12">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle <?php echo $isReadyToRun ? 'bg-white bg-opacity-20 text-white' : 'bg-warning bg-opacity-20 text-warning'; ?>">
                        <i class="fa-solid <?php echo $isReadyToRun ? 'fa-lock-open fa-2x' : 'fa-lock fa-2x'; ?>"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1 <?php echo $isReadyToRun ? 'text-white' : 'text-dark'; ?>">
                            <?php echo $isReadyToRun ? 'Payroll Run Gate: UNLOCKED & READY' : 'Payroll Run Gate: STRICTLY LOCKED'; ?>
                        </h4>
                        <p class="mb-0 small <?php echo $isReadyToRun ? 'text-white-50' : 'text-danger fw-semibold'; ?>">
                            <?php if ($isReadyToRun): ?>
                                All 14 mandatory validation checkpoints have been passed. You may now execute the monthly payroll calculation for <strong><?php echo htmlspecialchars($pay_month); ?></strong>.
                            <?php else: ?>
                                <strong>Payroll cannot be processed.</strong> <?php echo $blockingErrors; ?> blocking issue(s) require your resolution before payroll can execute.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-md-12 text-lg-end">
                <?php if ($isReadyToRun): ?>
                <form action="index.php?route=payroll/executeRun" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to execute the official payroll calculation for <?php echo htmlspecialchars($pay_month); ?>?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month); ?>">
                    <input type="hidden" name="calendar_days" value="<?php echo date('t', strtotime($pay_month . '-01')); ?>">
                    <input type="hidden" name="pay_date" value="<?php echo date('Y-m-01', strtotime('+1 month', strtotime($pay_month . '-01'))); ?>">
                    <input type="hidden" name="title" value="<?php echo date('F Y', strtotime($pay_month . '-01')) . ' Payroll'; ?>">

                    <?php if ($warningCount > 0): ?>
                    <div class="form-check form-check-inline text-white small mb-2 d-block text-lg-end">
                        <input class="form-check-input" type="checkbox" id="ackWarnings" required>
                        <label class="form-check-label text-white-50" for="ackWarnings">
                            I acknowledge <?php echo $warningCount; ?> non-blocking warning(s) & authorize calculation.
                        </label>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-light text-primary btn-lg rounded-pill px-5 shadow fw-bold">
                        <i class="fa-solid fa-play-circle me-2"></i> RUN PAYROLL NOW
                    </button>
                </form>
                <?php else: ?>
                <button type="button" class="btn btn-secondary btn-lg rounded-pill px-5 opacity-75" disabled title="Resolve all blocking exceptions to unlock">
                    <i class="fa-solid fa-lock me-2"></i> RUN PAYROLL (LOCKED)
                </button>
                <div class="small text-muted mt-1">Resolve <?php echo $blockingErrors; ?> blocking item(s) to unlock</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- MODAL: Manual Pre-Payroll Adjustment -->
<div class="modal fade" id="manualEntryModal" tabindex="-1" aria-labelledby="manualEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?route=payroll/addAdjustment" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month); ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="manualEntryModalLabel">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Add Manual Payroll Adjustment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Target Employee *</label>
                        <select name="employee_id" id="manual_employee_id" class="form-select rounded-3" required>
                            <option value="">-- Select Active Employee --</option>
                            <?php foreach ($employees_list as $emp): ?>
                            <option value="<?php echo (int)$emp->employee_id; ?>">
                                <?php echo htmlspecialchars($emp->employee_name); ?> (<?php echo htmlspecialchars($emp->employee_code); ?> - <?php echo htmlspecialchars($emp->department); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Adjustment Category *</label>
                            <select name="category" id="manual_category" class="form-select rounded-3" required>
                                <option value="lop">Loss of Pay (LOP)</option>
                                <option value="incentives">Incentives / Variable Pay</option>
                                <option value="deductions">Salary Deduction / Advance</option>
                                <option value="bonus">Bonus / Performance Pay</option>
                                <option value="arrears">Pending Arrears</option>
                                <option value="salary_revisions">Salary Revision</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Amount (₹) / Value</label>
                            <input type="number" step="0.01" name="amount" id="manual_amount" class="form-control rounded-3" placeholder="0.00" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Days / Units (For LOP or Arrear Days)</label>
                        <input type="number" step="0.5" name="days" id="manual_days" class="form-control rounded-3" placeholder="0.0" value="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Mandatory Reason / Justification *</label>
                        <input type="text" name="reason" class="form-control rounded-3" placeholder="e.g. Approved medical leave LOP deduction / Q3 sales achievement bonus" required>
                        <div class="form-text">All manual overrides require an audited business reason.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Remarks / Supporting Reference</label>
                        <textarea name="remarks" class="form-control rounded-3" rows="2" placeholder="Ticket number, HR approval email reference, etc."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-save me-1"></i> Save & Re-Validate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Bulk CSV Upload -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?route=payroll/uploadTemplate" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month); ?>">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="bulkUploadModalLabel">
                        <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Bulk Upload Payroll Inputs (CSV)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Upload Category *</label>
                        <select name="category" id="upload_category" class="form-select rounded-3" required onchange="updateTemplateLink(this.value)">
                            <option value="lop">Loss of Pay (LOP)</option>
                            <option value="incentives">Incentives / Variable Pay</option>
                            <option value="deductions">Salary Deductions / Advances</option>
                            <option value="bonus">Bonus Allocations</option>
                            <option value="arrears">Pending Arrears</option>
                            <option value="salary_revisions">Salary Revisions</option>
                        </select>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark small">Predefined Standard Template</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Download format containing mandatory headers & sample data.</div>
                        </div>
                        <a id="downloadTemplateBtn" href="index.php?route=payroll/downloadTemplate/lop" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="fa-solid fa-download me-1"></i> Sample CSV
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Select CSV File *</label>
                        <input type="file" name="template_file" class="form-control rounded-3" accept=".csv" required>
                        <div class="form-text">Ensure employee codes exist in active directory. Invalid records will generate an error log.</div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-file-import me-1"></i> Upload & Validate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Resolve / Override Exception -->
<div class="modal fade" id="resolveExceptionModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?route=payroll/resolveException" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month); ?>">
                <input type="hidden" name="exception_id" id="resolve_exception_id" value="">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="resolveModalLabel">
                        <i class="fa-solid fa-wrench text-warning me-2"></i>Resolve / Override Exception
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body py-4">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="small text-muted fw-semibold">Target / Employee:</div>
                        <div class="fw-bold text-dark mb-2" id="resolve_employee_target">-</div>
                        <div class="small text-muted fw-semibold">Issue Description:</div>
                        <div class="text-danger small" id="resolve_issue_text">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Resolution / Override Reason *</label>
                        <textarea name="resolution_reason" class="form-control rounded-3" rows="3" placeholder="Provide justification or actions taken (e.g. Account details verified manually, HR authorized exception)." required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">
                        <i class="fa-solid fa-check me-1"></i> Confirm & Clear Exception
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openManualEntry(category) {
    var catSelect = document.getElementById('manual_category');
    if (catSelect) {
        catSelect.value = category;
    }
    var modal = new bootstrap.Modal(document.getElementById('manualEntryModal'));
    modal.show();
}

function openBulkUpload(category) {
    var catSelect = document.getElementById('upload_category');
    if (catSelect) {
        catSelect.value = category;
        updateTemplateLink(category);
    }
    var modal = new bootstrap.Modal(document.getElementById('bulkUploadModal'));
    modal.show();
}

function updateTemplateLink(category) {
    var link = document.getElementById('downloadTemplateBtn');
    if (link) {
        link.href = 'index.php?route=payroll/downloadTemplate/' + encodeURIComponent(category);
    }
}

function openResolveModal(id, issue, target) {
    document.getElementById('resolve_exception_id').value = id;
    document.getElementById('resolve_issue_text').textContent = issue;
    document.getElementById('resolve_employee_target').textContent = target;
    var modal = new bootstrap.Modal(document.getElementById('resolveExceptionModal'));
    modal.show();
}
</script>
