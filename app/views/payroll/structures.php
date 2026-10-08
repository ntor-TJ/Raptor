<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=payroll/index" class="text-decoration-none">Payroll</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Salary Structures</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa-solid fa-id-card-clip text-primary me-2"></i>Employee Salary Profiles & Revision History
            </h1>
            <p class="text-muted small mb-0">Configure monthly CTC structures, statutory allowances (Basic, HRA, Special), bank account details, and track complete chronological appraisal salary revision histories.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=payroll/index" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Payroll
            </a>
            <a href="index.php?route=payroll/readiness" class="btn btn-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-shield-halved me-1"></i> Readiness Center
            </a>
        </div>
    </div>

    <!-- Success Flash Message -->
    <?php if (!empty($_SESSION['salary_structure_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-check fa-2x me-3 text-success"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Profile & Revision Saved</h5>
                <div class="small"><?php echo htmlspecialchars($_SESSION['salary_structure_success']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['salary_structure_success']); endif; ?>

    <!-- Filter & Search Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="index.php?route=payroll/structures" class="btn btn-sm <?php echo empty($emp_type) ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        All Profiles (<?php echo count($structures); ?>)
                    </a>
                    <a href="index.php?route=payroll/structures&type=full_time_pf" class="btn btn-sm <?php echo ($emp_type === 'full_time_pf') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Full-Time (PF)
                    </a>
                    <a href="index.php?route=payroll/structures&type=consultant" class="btn btn-sm <?php echo ($emp_type === 'consultant') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Consultants
                    </a>
                    <a href="index.php?route=payroll/structures&type=bench" class="btn btn-sm <?php echo ($emp_type === 'bench') ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
                        Bench
                    </a>
                </div>

                <form action="index.php" method="GET" class="d-flex gap-2" style="max-width: 320px;">
                    <input type="hidden" name="route" value="payroll/structures">
                    <?php if (!empty($emp_type)): ?>
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($emp_type); ?>">
                    <?php endif; ?>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control rounded-start-pill" placeholder="Search employee..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary rounded-end-pill" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase fw-semibold">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Type</th>
                        <th>Monthly CTC</th>
                        <th>Base & HRA</th>
                        <th>Statutory</th>
                        <th>Bank Details</th>
                        <th>Statutory IDs</th>
                        <th class="text-end pe-4">Action & History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($structures)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-id-card-clip fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                            No salary structures found matching criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($structures as $st): 
                        $revList = $revisions_grouped[(int)$st->employee_id] ?? [];
                        $revCount = count($revList);
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($st->employee_name); ?></div>
                            <div class="text-muted small">
                                <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($st->employee_code); ?></span>
                                <?php echo htmlspecialchars($st->designation); ?> &bull; <?php echo htmlspecialchars($st->department); ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $typeLabel = 'Full-Time (PF)';
                            $typeBadge = 'bg-primary-soft text-primary';
                            if ($st->employment_type === 'consultant') {
                                $typeLabel = 'Consultant';
                                $typeBadge = 'bg-info-soft text-info';
                            } elseif ($st->employment_type === 'bench') {
                                $typeLabel = 'Bench';
                                $typeBadge = 'bg-secondary-soft text-secondary';
                            }
                            ?>
                            <span class="badge <?php echo $typeBadge; ?> rounded-pill px-2 py-1 small"><?php echo $typeLabel; ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">₹<?php echo number_format($st->monthly_ctc, 2); ?></div>
                            <div class="text-muted small">Annual: ₹<?php echo number_format($st->monthly_ctc * 12, 0); ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">Basic: ₹<?php echo number_format($st->base_salary, 0); ?></div>
                            <div class="text-muted small">HRA: ₹<?php echo number_format($st->hra, 0); ?> | Special: ₹<?php echo number_format($st->special_allowance, 0); ?></div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php if ($st->pf_applicable): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success" style="font-size: 0.68rem;">PF</span>
                                <?php endif; ?>
                                <?php if ($st->pt_applicable): ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info" style="font-size: 0.68rem;">PT</span>
                                <?php endif; ?>
                                <?php if ($st->esi_applicable): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning" style="font-size: 0.68rem;">ESI</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($st->bank_account_number): ?>
                            <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($st->bank_name ?: 'Bank'); ?></div>
                            <div class="text-muted font-monospace small" style="font-size: 0.75rem;">
                                A/C: <?php echo htmlspecialchars($st->bank_account_number); ?><br>
                                IFSC: <?php echo htmlspecialchars($st->bank_ifsc); ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small fst-italic">No bank info</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-muted small" style="font-size: 0.75rem;">
                                <?php if ($st->pan_number): ?>PAN: <strong class="text-dark"><?php echo htmlspecialchars($st->pan_number); ?></strong><br><?php endif; ?>
                                <?php if ($st->uan_number): ?>UAN: <span class="text-dark"><?php echo htmlspecialchars($st->uan_number); ?></span><br><?php endif; ?>
                                <?php if ($st->pf_number): ?>PF: <span class="text-dark"><?php echo htmlspecialchars($st->pf_number); ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 text-nowrap position-relative" data-bs-toggle="modal" data-bs-target="#historyModal_<?php echo (int)$st->employee_id; ?>" title="View Revision History">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> History
                                    <?php if ($revCount > 0): ?>
                                    <span class="badge bg-primary text-white rounded-pill ms-1" style="font-size: 0.65rem;"><?php echo $revCount; ?></span>
                                    <?php endif; ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 text-nowrap" data-bs-toggle="modal" data-bs-target="#editStructureModal_<?php echo (int)$st->employee_id; ?>">
                                    <i class="fa-solid fa-pen me-1"></i> Edit Profile
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal: Edit Structure & Record Revision -->
                    <div class="modal fade" id="editStructureModal_<?php echo (int)$st->employee_id; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <form action="index.php?route=payroll/saveStructure" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="employee_id" value="<?php echo (int)$st->employee_id; ?>">

                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-dark">
                                            <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Salary Profile &bull; <?php echo htmlspecialchars($st->employee_name); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body py-3">
                                        <!-- Revision Parameters Section -->
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-4 mb-3 border border-primary border-opacity-25">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="text-uppercase text-primary fw-bold small mb-0">
                                                    <i class="fa-solid fa-calendar-check me-1"></i> Revision & Effective Schedule
                                                </h6>
                                                <span class="badge bg-primary text-white rounded-pill px-2 py-1 small" style="font-size: 0.7rem;">Appraisal Audit Tracking</span>
                                            </div>
                                            
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Effective Date *</label>
                                                    <input type="date" name="effective_date" class="form-control rounded-3" value="<?php echo date('Y-m-d'); ?>" required>
                                                    <div class="form-text text-muted" style="font-size: 0.72rem;">Effective date of revision.</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Effective Pay Month *</label>
                                                    <input type="month" name="effective_pay_month" class="form-control rounded-3" value="<?php echo date('Y-m'); ?>" required>
                                                    <div class="form-text text-muted" style="font-size: 0.72rem;">Payroll cycle month for hike.</div>
                                                </div>
                                                <div class="col-md-4 d-flex align-items-center">
                                                    <div class="form-check mt-3">
                                                        <input class="form-check-input" type="checkbox" name="requires_arrears" value="1" id="arr_<?php echo (int)$st->employee_id; ?>" checked>
                                                        <label class="form-check-label small fw-semibold text-dark" for="arr_<?php echo (int)$st->employee_id; ?>">
                                                            Calculate Retro Arrears
                                                        </label>
                                                        <div class="text-muted" style="font-size: 0.7rem;">Auto-apply hike difference as arrears.</div>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-2">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Revision Reason / Justification</label>
                                                    <input type="text" name="revision_reason" class="form-control rounded-3" placeholder="e.g. Annual Appraisal Hike, Promotion to Senior Engineer, Role Band Adjustment">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-secondary">Monthly CTC (₹) *</label>
                                                <input type="number" step="0.01" name="monthly_ctc" class="form-control rounded-3 fw-bold text-primary" value="<?php echo (float)$st->monthly_ctc; ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-secondary">Employment Category *</label>
                                                <select name="employment_type" class="form-select rounded-3">
                                                    <option value="full_time_pf" <?php echo ($st->employment_type === 'full_time_pf') ? 'selected' : ''; ?>>Full-Time with PF</option>
                                                    <option value="consultant" <?php echo ($st->employment_type === 'consultant') ? 'selected' : ''; ?>>Consultant / Contract (No PF)</option>
                                                    <option value="bench" <?php echo ($st->employment_type === 'bench') ? 'selected' : ''; ?>>Bench Employee</option>
                                                    <option value="trainer_hourly" <?php echo ($st->employment_type === 'trainer_hourly') ? 'selected' : ''; ?>>Trainer / Hourly</option>
                                                </select>
                                            </div>
                                        </div>

                                        <h6 class="text-uppercase text-secondary fw-bold small mb-2 border-bottom pb-1">Allowance Components (Leave empty to auto-calculate ~50% Basic, ~50% HRA)</h6>
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">Basic Salary (₹)</label>
                                                <input type="number" step="0.01" name="base_salary" class="form-control rounded-3" value="<?php echo (float)$st->base_salary; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">HRA (₹)</label>
                                                <input type="number" step="0.01" name="hra" class="form-control rounded-3" value="<?php echo (float)$st->hra; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">Special Allowance (₹)</label>
                                                <input type="number" step="0.01" name="special_allowance" class="form-control rounded-3" value="<?php echo (float)$st->special_allowance; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">Other Allowance (₹)</label>
                                                <input type="number" step="0.01" name="other_allowance" class="form-control rounded-3" value="<?php echo (float)$st->other_allowance; ?>">
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-4 mb-3 p-3 bg-light rounded-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="pf_<?php echo (int)$st->employee_id; ?>" <?php echo $st->pf_applicable ? 'checked' : ''; ?>>
                                                <label class="form-check-label small fw-semibold text-dark" for="pf_<?php echo (int)$st->employee_id; ?>">PF Applicable (12% capped at ₹1,800)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="pt_applicable" value="1" id="pt_<?php echo (int)$st->employee_id; ?>" <?php echo $st->pt_applicable ? 'checked' : ''; ?>>
                                                <label class="form-check-label small fw-semibold text-dark" for="pt_<?php echo (int)$st->employee_id; ?>">Professional Tax (₹200/month)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="esi_applicable" value="1" id="esi_<?php echo (int)$st->employee_id; ?>" <?php echo $st->esi_applicable ? 'checked' : ''; ?>>
                                                <label class="form-check-label small fw-semibold text-dark" for="esi_<?php echo (int)$st->employee_id; ?>">ESI Applicable (Gross ≤ ₹21,000)</label>
                                            </div>
                                        </div>

                                        <h6 class="text-uppercase text-secondary fw-bold small mb-2 border-bottom pb-1">Bank & Disbursement Info</h6>
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">Bank Name</label>
                                                <input type="text" name="bank_name" class="form-control rounded-3" value="<?php echo htmlspecialchars($st->bank_name ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">Account Number</label>
                                                <input type="text" name="bank_account_number" class="form-control rounded-3" value="<?php echo htmlspecialchars($st->bank_account_number ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">IFSC Code</label>
                                                <input type="text" name="bank_ifsc" class="form-control rounded-3" value="<?php echo htmlspecialchars($st->bank_ifsc ?? ''); ?>">
                                            </div>
                                        </div>

                                        <h6 class="text-uppercase text-secondary fw-bold small mb-2 border-bottom pb-1">Statutory Identifiers</h6>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">PAN Number</label>
                                                <input type="text" name="pan_number" class="form-control rounded-3 text-uppercase" value="<?php echo htmlspecialchars($st->pan_number ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">UAN Number</label>
                                                <input type="text" name="uan_number" class="form-control rounded-3" value="<?php echo htmlspecialchars($st->uan_number ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">PF Number</label>
                                                <input type="text" name="pf_number" class="form-control rounded-3" value="<?php echo htmlspecialchars($st->pf_number ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                                            <i class="fa-solid fa-save me-1"></i> Save Profile & Revision
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: Revision History -->
                    <div class="modal fade" id="historyModal_<?php echo (int)$st->employee_id; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header border-0 pb-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle">
                                            <i class="fa-solid fa-clock-rotate-left fa-lg"></i>
                                        </div>
                                        <div>
                                            <h5 class="modal-title fw-bold text-dark mb-0">Salary Revision History &bull; <?php echo htmlspecialchars($st->employee_name); ?></h5>
                                            <div class="text-muted small"><?php echo htmlspecialchars($st->employee_code); ?> &bull; Current CTC: <strong class="text-success">₹<?php echo number_format($st->monthly_ctc, 2); ?>/mo</strong></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body py-4">
                                    <?php if (empty($revList)): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-receipt fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        <h6 class="fw-bold text-dark">No Historical Salary Revisions</h6>
                                        <p class="small text-muted mb-0">This employee is currently on their baseline salary structure. Revisions made during appraisals or profile edits will be chronologically logged here.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light text-secondary small text-uppercase fw-semibold">
                                                <tr>
                                                    <th class="ps-3">Effective Date</th>
                                                    <th>Pay Month</th>
                                                    <th>Previous CTC</th>
                                                    <th>Revised CTC</th>
                                                    <th>Hike Difference</th>
                                                    <th>Arrears</th>
                                                    <th>Reason & Approver</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($revList as $rv): 
                                                    $diff = (float)$rv->difference_amount;
                                                    $pct = ($rv->previous_ctc > 0) ? round(($diff / $rv->previous_ctc) * 100, 1) : 0;
                                                ?>
                                                <tr>
                                                    <td class="ps-3 fw-semibold text-dark small">
                                                        <?php echo date('d M Y', strtotime($rv->effective_date)); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border px-2 py-1 small"><?php echo htmlspecialchars($rv->effective_pay_month); ?></span>
                                                    </td>
                                                    <td class="small text-muted">
                                                        ₹<?php echo number_format($rv->previous_ctc, 2); ?>
                                                    </td>
                                                    <td class="small fw-bold text-dark">
                                                        ₹<?php echo number_format($rv->revised_ctc, 2); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($diff > 0): ?>
                                                        <span class="text-success fw-bold small">+₹<?php echo number_format($diff, 2); ?> <span class="badge bg-success bg-opacity-10 text-success ms-1">+<?php echo $pct; ?>%</span></span>
                                                        <?php elseif ($diff < 0): ?>
                                                        <span class="text-danger fw-bold small">-₹<?php echo number_format(abs($diff), 2); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">₹0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($rv->requires_arrears): ?>
                                                        <span class="badge bg-warning bg-opacity-10 text-warning px-2 py-1 rounded-pill small">Yes (Arrears)</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-light text-muted border px-2 py-1 rounded-pill small">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small">
                                                        <div class="text-dark fw-semibold"><?php echo htmlspecialchars($rv->reason ?: 'Salary Revision'); ?></div>
                                                        <div class="text-muted" style="font-size: 0.72rem;">
                                                            By: <?php echo htmlspecialchars($rv->approved_by_name ?: 'HR / Admin'); ?> &bull; <?php echo date('d M Y', strtotime($rv->created_at)); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
