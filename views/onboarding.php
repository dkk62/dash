<?php
$pageTitle = 'Onboarding';
$fd = $formData ?? [];
$isClient   = (currentRole() === 'client');
$isNew      = in_array($formStatus, ['new', 'draft']);
$isEdit     = !$isNew;
$canEdit    = $isClient;
$canReview  = hasRole(['admin']) && $formStatus === 'submitted';
$sectionsSaved = $fd['_sections_saved'] ?? [];
$stagedFiles   = $fd['_staged_files'] ?? [];

$val = function(string $key, string $default = '') use ($fd): string {
    return e((string) ($fd[$key] ?? $default));
};
$checked = function(string $key, string $value) use ($fd): string {
    return (($fd[$key] ?? '') === $value) ? 'checked' : '';
};
$inArray = function(string $key, string $value) use ($fd): string {
    return in_array($value, (array) ($fd[$key] ?? [])) ? 'checked' : '';
};
$tabDone = function(string $section) use ($sectionsSaved): bool {
    return in_array($section, $sectionsSaved);
};

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-clipboard-check"></i> Client Onboarding</h4>
    <div class="d-flex align-items-center gap-2">
        <?php if (!$isClient): ?>
            <span class="badge bg-<?= match($formStatus) { 'submitted' => 'warning', 'reviewed' => 'success', 'draft' => 'secondary', default => 'light text-dark' } ?> fs-6">
                <?= e(ucfirst($formStatus === 'new' ? 'Not Started' : $formStatus)) ?>
            </span>
        <?php endif; ?>
        <span class="text-muted small" id="onbSaveStatus"></span>
    </div>
</div>

<?php if (!empty($clientEntities) && count($clientEntities) > 1): ?>
<div class="alert alert-secondary py-2 mb-3 d-flex align-items-center flex-wrap gap-2">
    <strong class="me-2"><i class="bi bi-building"></i> Select Entity:</strong>
    <?php foreach ($clientEntities as $ent): ?>
        <?php
            $isCurrent = ($ent['id'] === (int)$client['id']);
            $entStatus = $ent['status'];
            $entBadge  = match($entStatus) {
                'submitted' => 'bg-primary',
                'reviewed'  => 'bg-success',
                'draft'     => 'bg-secondary',
                default     => 'bg-warning text-dark',
            };
            $entLabel  = match($entStatus) {
                'submitted' => 'Submitted',
                'reviewed'  => 'Reviewed',
                'draft'     => 'Draft',
                default     => 'Not Started',
            };
        ?>
        <a href="<?= e(appUrl('?action=onboarding&client_id=' . $ent['id'])) ?>"
           class="btn btn-sm <?= $isCurrent ? 'btn-dark' : 'btn-outline-dark' ?>">
            <?= e($ent['name']) ?>
            <span class="badge <?= $entBadge ?> ms-1"><?= $entLabel ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isClient && $isEdit): ?>
<div class="alert alert-info small mb-3">
    <i class="bi bi-exclamation-triangle text-warning"></i> <strong>Editing mode:</strong> File uploads are disabled. Please upload new or updated documents via the <a href="<?= e(appUrl('?action=documents')) ?>">Documents</a> section.
</div>
<?php elseif ($isClient && $isNew): ?>
<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle"></i> Complete each tab and click <strong>Save</strong>. You can save your progress and return later. Files are staged until you do the final <strong>Submit</strong> on the last tab.
</div>
<?php endif; ?>

<input type="hidden" id="onbCsrfToken" value="<?= e(csrfToken()) ?>">
<input type="hidden" id="onbClientId" value="<?= (int) $client['id'] ?>">
<input type="hidden" id="onbIsEdit" value="<?= $isEdit ? '1' : '0' ?>">
<input type="hidden" id="onbCanEdit" value="<?= $canEdit ? '1' : '0' ?>">

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-0" id="onbTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-business" type="button" role="tab">
            <i class="bi bi-building"></i> <span class="d-none d-md-inline">Business Info</span>
            <?php if ($tabDone('business_info')): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-banking" type="button" role="tab">
            <i class="bi bi-bank"></i> <span class="d-none d-md-inline">Banking</span>
            <?php if ($tabDone('banking')): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-income" type="button" role="tab">
            <i class="bi bi-cash-stack"></i> <span class="d-none d-md-inline">Income</span>
            <?php if ($tabDone('income_property')): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employees" type="button" role="tab">
            <i class="bi bi-people"></i> <span class="d-none d-md-inline">People</span>
            <?php if ($tabDone('employees')): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tax" type="button" role="tab">
            <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Tax & Notes</span>
            <?php if ($tabDone('tax_compliance')): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button" role="tab">
            <i class="bi bi-file-earmark-arrow-up"></i> <span class="d-none d-md-inline">Documents</span>
        </button>
    </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom p-3 mb-4" id="onbTabContent">

<!-- ==================== TAB 1: Business Info ==================== -->
<div class="tab-pane fade show active" id="tab-business" role="tabpanel" data-section="business_info">
    <h5 class="mb-3">1. Business Information</h5>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Business Name</label>
            <input type="text" name="business_name" class="form-control form-control-sm" value="<?= e($client['name']) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">EIN</label>
            <input type="text" name="ein" class="form-control form-control-sm" value="<?= $val('ein') ?>" <?= $canEdit ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-6">
            <label class="form-label">Entity Type (LLC / S-Corp / etc.)</label>
            <input type="text" name="entity_type" class="form-control form-control-sm" value="<?= $val('entity_type') ?>" <?= $canEdit ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-6">
            <label class="form-label">Start Date of Business</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $val('start_date') ?>" <?= $canEdit ? '' : 'readonly' ?>>
        </div>
    </div>
    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <div></div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm onb-save-btn"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-outline-primary btn-sm onb-next-btn">Next <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== TAB 2: Banking & Payments ==================== -->
<div class="tab-pane fade" id="tab-banking" role="tabpanel" data-section="banking">
    <h5 class="mb-3">2. Banking &amp; Credit Cards</h5>
    <p class="text-muted small">Upload PDF statements to the Documents tab. Monthly clients: month-by-month YTD. Yearly clients: full 12-month period.</p>

    <!-- Bank Accounts -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Bank Accounts (Checking / Savings)</h6>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="bankAccountRows"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Bank Name</th><th>Account Number</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="bankAccountRows">
            <?php
            $baRows = $fd['bank_accounts'] ?? [['bank'=>'','account'=>'']];
            if (!is_array($baRows) || (isset($baRows[0]) && !is_array($baRows[0]))) $baRows = [['bank'=>'','account'=>'']];
            foreach ($baRows as $ba): ?>
                <tr>
                    <td><input type="text" name="ba_bank[]" class="form-control form-control-sm" value="<?= e($ba['bank'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ba_account[]" class="form-control form-control-sm" value="<?= e($ba['account'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Credit Cards -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Credit Cards</h6>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="creditCardRows"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Bank / Issuer</th><th>Account Number</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="creditCardRows">
            <?php
            $ccRows = $fd['credit_cards'] ?? [['bank'=>'','account'=>'']];
            if (!is_array($ccRows) || (isset($ccRows[0]) && !is_array($ccRows[0]))) $ccRows = [['bank'=>'','account'=>'']];
            foreach ($ccRows as $cc): ?>
                <tr>
                    <td><input type="text" name="cc_bank[]" class="form-control form-control-sm" value="<?= e($cc['bank'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="cc_account[]" class="form-control form-control-sm" value="<?= e($cc['account'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Loans -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Loans</h6>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="loanRows"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Lender / Bank</th><th>Account Number</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="loanRows">
            <?php
            $lnRows = $fd['loans'] ?? [['bank'=>'','account'=>'']];
            if (!is_array($lnRows) || (isset($lnRows[0]) && !is_array($lnRows[0]))) $lnRows = [['bank'=>'','account'=>'']];
            foreach ($lnRows as $ln): ?>
                <tr>
                    <td><input type="text" name="ln_bank[]" class="form-control form-control-sm" value="<?= e($ln['bank'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ln_account[]" class="form-control form-control-sm" value="<?= e($ln['account'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Merchant Accounts -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Merchant Accounts (Stripe, Square, Zelle, etc.)</h6>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="merchantRows"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
    </div>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Account Name</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="merchantRows">
            <?php
            $mrRows = $fd['merchant_accounts'] ?? [['name'=>'']];
            if (!is_array($mrRows) || (isset($mrRows[0]) && !is_array($mrRows[0]))) $mrRows = [['name'=>'']];
            foreach ($mrRows as $mr): ?>
                <tr>
                    <td><input type="text" name="mr_name[]" class="form-control form-control-sm" value="<?= e($mr['name'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr class="my-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">3. Automatic Payments (Fixed Monthly Expenses)</h5>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="autoPayRows"><i class="bi bi-plus-lg"></i> Add Row</button><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Vendor</th><th>Amount</th><th>Monthly?</th><th>Category</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="autoPayRows">
            <?php
            $apRows = $fd['auto_payments'] ?? [
                ['vendor'=>'Rent','amount'=>'','monthly'=>'','category'=>''],
                ['vendor'=>'Utilities','amount'=>'','monthly'=>'','category'=>''],
                ['vendor'=>'Internet','amount'=>'','monthly'=>'','category'=>''],
                ['vendor'=>'Insurance','amount'=>'','monthly'=>'','category'=>''],
                ['vendor'=>'Software Subscriptions','amount'=>'','monthly'=>'','category'=>''],
                ['vendor'=>'Other','amount'=>'','monthly'=>'','category'=>''],
            ];
            foreach ($apRows as $ap): ?>
                <tr>
                    <td><input type="text" name="ap_vendor[]" class="form-control form-control-sm" value="<?= e($ap['vendor'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ap_amount[]" class="form-control form-control-sm" value="<?= e($ap['amount'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><select name="ap_monthly[]" class="form-select form-select-sm" <?= $canEdit ? '' : 'disabled' ?>><option value="">--</option><option value="yes" <?= ($ap['monthly']??'')==='yes'?'selected':'' ?>>Yes</option><option value="no" <?= ($ap['monthly']??'')==='no'?'selected':'' ?>>No</option></select></td>
                    <td><input type="text" name="ap_category[]" class="form-control form-control-sm" value="<?= e($ap['category'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canEdit && !$isEdit): ?>
    <div class="mt-3">
        <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Upload Bank / Card Statements (PDF)</label>
        <input type="file" name="bank_statements[]" class="form-control form-control-sm onb-file-input" multiple accept=".pdf">
        <div class="onb-file-list mt-1"></div>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm onb-prev-btn"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm onb-save-btn"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-outline-primary btn-sm onb-next-btn">Next <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== TAB 3: Income & Property ==================== -->
<div class="tab-pane fade" id="tab-income" role="tabpanel" data-section="income_property">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">4. Rental Income (If Applicable)</h5>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="rentalRows"><i class="bi bi-plus-lg"></i> Add Row</button><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Property Address</th><th>Tenant Name</th><th>Monthly Rent</th><th>Deposit Held</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="rentalRows">
            <?php
            $riRows = $fd['rental_income'] ?? [['address'=>'','tenant'=>'','rent'=>'','deposit'=>'']];
            foreach ($riRows as $ri): ?>
                <tr>
                    <td><input type="text" name="ri_address[]" class="form-control form-control-sm" value="<?= e($ri['address'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ri_tenant[]" class="form-control form-control-sm" value="<?= e($ri['tenant'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ri_rent[]" class="form-control form-control-sm" value="<?= e($ri['rent'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <td><input type="text" name="ri_deposit[]" class="form-control form-control-sm" value="<?= e($ri['deposit'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr class="my-3">
    <h5 class="mb-3">5. Fixed Monthly Expenses</h5>
    <div class="row g-2">
        <?php foreach (['Mortgage Payments','HOA Fees','Property Taxes','Insurance','Management Fees','Maintenance Contracts'] as $opt): ?>
        <div class="col-md-4"><div class="form-check">
            <input class="form-check-input" type="checkbox" name="fixed_expenses[]" value="<?= e($opt) ?>" id="fe_<?= e(str_replace(' ','_',$opt)) ?>" <?= $inArray('fixed_expenses', $opt) ?> <?= $canEdit ? '' : 'disabled' ?>>
            <label class="form-check-label" for="fe_<?= e(str_replace(' ','_',$opt)) ?>"><?= e($opt) ?></label>
        </div></div>
        <?php endforeach; ?>
    </div>

    <hr class="my-3">
    <h5 class="mb-3">9. Income Sources</h5>
    <p class="text-muted small mb-2">How do you receive income? (Check all that apply)</p>
    <div class="row g-2">
        <?php foreach (['Zelle','Cash App','Checks','Bank Transfer','Property Management'] as $src): ?>
        <div class="col-md-4"><div class="form-check">
            <input class="form-check-input" type="checkbox" name="income_sources[]" value="<?= e($src) ?>" id="is_<?= e(str_replace(' ','_',$src)) ?>" <?= $inArray('income_sources', $src) ?> <?= $canEdit ? '' : 'disabled' ?>>
            <label class="form-check-label" for="is_<?= e(str_replace(' ','_',$src)) ?>"><?= e($src) ?></label>
        </div></div>
        <?php endforeach; ?>
        <div class="col-md-4"><div class="form-check">
            <input class="form-check-input" type="checkbox" name="income_sources[]" value="Other" id="is_Other" <?= $inArray('income_sources', 'Other') ?> <?= $canEdit ? '' : 'disabled' ?>>
            <label class="form-check-label" for="is_Other">Other</label>
        </div></div>
        <div class="col-md-8">
            <input type="text" name="income_source_other" class="form-control form-control-sm" placeholder="If Other, specify..." value="<?= $val('income_source_other') ?>" <?= $canEdit ? '' : 'readonly' ?>>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm onb-prev-btn"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm onb-save-btn"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-outline-primary btn-sm onb-next-btn">Next <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== TAB 4: Employees & Contractors ==================== -->
<div class="tab-pane fade" id="tab-employees" role="tabpanel" data-section="employees">
    <h5 class="mb-3">6. Employees</h5>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Do you have employees?</label>
            <div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_employees" value="yes" id="emp_yes" <?= $checked('has_employees','yes') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="emp_yes">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_employees" value="no" id="emp_no" <?= $checked('has_employees','no') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="emp_no">No</label></div>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Payroll handled by a provider (Gusto, ADP)?</label>
            <div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_payroll_provider" value="yes" id="pp_yes" <?= $checked('has_payroll_provider','yes') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="pp_yes">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_payroll_provider" value="no" id="pp_no" <?= $checked('has_payroll_provider','no') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="pp_no">No</label></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-4"><label class="form-label">Provider Name</label><input type="text" name="payroll_provider_name" class="form-control form-control-sm" value="<?= $val('payroll_provider_name') ?>" <?= $canEdit?'':'readonly' ?>></div>
        <div class="col-md-4"><label class="form-label">Pay Frequency</label><input type="text" name="pay_frequency" class="form-control form-control-sm" value="<?= $val('pay_frequency') ?>" <?= $canEdit?'':'readonly' ?>></div>
        <div class="col-md-4"><label class="form-label">Number of Employees</label><input type="text" name="num_employees" class="form-control form-control-sm" value="<?= $val('num_employees') ?>" <?= $canEdit?'':'readonly' ?>></div>
    </div>
    <p class="text-muted small mt-2"><i class="bi bi-exclamation-circle"></i> If payroll is not handled by a provider, upload an Employee List to the Documents tab.</p>

    <?php if ($canEdit && !$isEdit): ?>
    <div class="mt-2">
        <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Upload Employee List (PDF)</label>
        <input type="file" name="employee_list[]" class="form-control form-control-sm onb-file-input" multiple accept=".pdf">
        <div class="onb-file-list mt-1"></div>
    </div>
    <?php endif; ?>

    <hr class="my-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">7. Related Party Payments</h5>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="relativeRows"><i class="bi bi-plus-lg"></i> Add Row</button><?php endif; ?>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Do you pay any relatives from the business?</label>
            <div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pays_relatives" value="yes" id="rel_y" <?= $checked('pays_relatives','yes') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="rel_y">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pays_relatives" value="no" id="rel_n" <?= $checked('pays_relatives','no') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="rel_n">No</label></div>
            </div>
        </div>
    </div>
    <p class="text-muted small mb-2"><em>List them so we can categorize as Salary or Owner Drawings.</em></p>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Relationship</th><th>Amount</th><th>Salary or Drawings</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="relativeRows">
            <?php
            $relRows = $fd['relatives'] ?? [['name'=>'','relationship'=>'','amount'=>'','pay_type'=>'']];
            foreach ($relRows as $ri => $rel): ?>
                <tr>
                    <td><input type="text" name="rel_name[]" class="form-control form-control-sm" value="<?= e($rel['name'] ?? '') ?>" <?= $canEdit?'':'readonly' ?>></td>
                    <td><input type="text" name="rel_relationship[]" class="form-control form-control-sm" value="<?= e($rel['relationship'] ?? '') ?>" <?= $canEdit?'':'readonly' ?>></td>
                    <td><input type="text" name="rel_amount[]" class="form-control form-control-sm" value="<?= e($rel['amount'] ?? '') ?>" <?= $canEdit?'':'readonly' ?>></td>
                    <td class="text-center text-nowrap">
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="rel_pay_type[<?= $ri ?>]" value="salary" <?= ($rel['pay_type']??($rel['as_salary']??'')==='yes'?'salary':'')==='salary'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label">Salary</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="rel_pay_type[<?= $ri ?>]" value="drawings" <?= ($rel['pay_type']??($rel['as_drawings']??'')==='yes'?'drawings':'')==='drawings'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label">Drawings</label></div>
                    </td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($canEdit && !$isEdit): ?>
    <div class="mt-2">
        <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Upload Relative Payment Details (PDF)</label>
        <input type="file" name="relative_details[]" class="form-control form-control-sm onb-file-input" multiple accept=".pdf">
        <div class="onb-file-list mt-1"></div>
    </div>
    <?php endif; ?>

    <hr class="my-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">8. Independent Contractors</h5>
        <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-dark add-row-btn" data-target="contractorRows"><i class="bi bi-plus-lg"></i> Add Row</button><?php endif; ?>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Do you pay contractors?</label>
            <div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pays_contractors" value="yes" id="con_y" <?= $checked('pays_contractors','yes') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="con_y">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pays_contractors" value="no" id="con_n" <?= $checked('pays_contractors','no') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="con_n">No</label></div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>Contractor Name</th><th>Service</th><th>Paid Monthly?</th><th>1099 Needed</th><?php if ($canEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="contractorRows">
            <?php
            $conRows = $fd['contractors'] ?? [['name'=>'','service'=>'','paid_monthly'=>'','needs_1099'=>'']];
            foreach ($conRows as $con): ?>
                <tr>
                    <td><input type="text" name="con_name[]" class="form-control form-control-sm" value="<?= e($con['name'] ?? '') ?>" <?= $canEdit?'':'readonly' ?>></td>
                    <td><input type="text" name="con_service[]" class="form-control form-control-sm" value="<?= e($con['service'] ?? '') ?>" <?= $canEdit?'':'readonly' ?>></td>
                    <td><select name="con_monthly[]" class="form-select form-select-sm" <?= $canEdit?'':'disabled' ?>><option value="">--</option><option value="yes" <?= ($con['paid_monthly']??'')==='yes'?'selected':'' ?>>Yes</option><option value="no" <?= ($con['paid_monthly']??'')==='no'?'selected':'' ?>>No</option></select></td>
                    <td><select name="con_1099[]" class="form-select form-select-sm" <?= $canEdit?'':'disabled' ?>><option value="">--</option><option value="yes" <?= ($con['needs_1099']??'')==='yes'?'selected':'' ?>>Yes</option><option value="no" <?= ($con['needs_1099']??'')==='no'?'selected':'' ?>>No</option></select></td>
                    <?php if ($canEdit): ?><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x-lg"></i></button></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="alert alert-warning small mt-3 mb-0">
        <strong><i class="bi bi-exclamation-triangle"></i> W-9 Requirements</strong>
        <p class="mb-2 mt-1">W-9s are required for accurate 1099 filing and IRS compliance.</p>
        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="w9_collected" value="yes" id="w9_collected" <?= ($fd['w9_collected']??'')==='yes'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="w9_collected">I have collected a W-9 from every contractor I pay</label></div>
        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="w9_submitted" value="yes" id="w9_submitted" <?= ($fd['w9_submitted']??'')==='yes'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="w9_submitted">I have submitted / will submit all W-9s to the office</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="w9_new_notify" value="yes" id="w9_new_notify" <?= ($fd['w9_new_notify']??'')==='yes'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="w9_new_notify">I will notify the office when adding new contractors</label></div>
    </div>

    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm onb-prev-btn"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm onb-save-btn"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-outline-primary btn-sm onb-next-btn">Next <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== TAB 5: Tax & Notes ==================== -->
<div class="tab-pane fade" id="tab-tax" role="tabpanel" data-section="tax_compliance">
    <h5 class="mb-3">10. Tax &amp; Compliance</h5>
    <p class="text-muted small mb-2">Upload prior year tax returns and financials via the Documents tab.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Does the business collect sales tax?</label>
            <div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="collects_sales_tax" value="yes" id="st_y" <?= $checked('collects_sales_tax','yes') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="st_y">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="collects_sales_tax" value="no" id="st_n" <?= $checked('collects_sales_tax','no') ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="st_n">No</label></div>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">States/jurisdictions registered</label>
            <input type="text" name="tax_jurisdictions" class="form-control form-control-sm" value="<?= $val('tax_jurisdictions') ?>" <?= $canEdit?'':'readonly' ?>>
        </div>
    </div>

    <?php if ($canEdit && !$isEdit): ?>
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Prior Year Tax Returns (PDF)</label>
            <input type="file" name="tax_returns[]" class="form-control form-control-sm onb-file-input" multiple accept=".pdf">
            <div class="onb-file-list mt-1"></div>
            <div class="form-text">Last two years of Federal and State returns.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Prior Year Financials (PDF)</label>
            <input type="file" name="financials[]" class="form-control form-control-sm onb-file-input" multiple accept=".pdf">
            <div class="onb-file-list mt-1"></div>
            <div class="form-text">Most recent P&amp;L and Balance Sheet.</div>
        </div>
    </div>
    <?php endif; ?>

    <hr class="my-3">
    <h5 class="mb-3">11. Business Status &amp; Annual Filings</h5>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="business_reinstated" value="yes" id="biz_reinstate" <?= ($fd['business_reinstated']??'')==='yes'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="biz_reinstate">Business has been reinstated (if inactive or expired)</label></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="annual_report_filed" value="yes" id="biz_annual" <?= ($fd['annual_report_filed']??'')==='yes'?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><label class="form-check-label" for="biz_annual">Annual Report filed for current year</label></div>
    <div class="alert alert-success small mb-0 mt-2"><i class="bi bi-check-circle"></i> Our office will reach out each year with reminders and assistance.</div>

    <hr class="my-3">
    <h5 class="mb-3">13. Additional Notes</h5>
    <textarea name="additional_notes" class="form-control form-control-sm" rows="4" placeholder="Any specific transactions or business details we should be aware of..." <?= $canEdit?'':'readonly' ?>><?= $val('additional_notes') ?></textarea>

    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm onb-prev-btn"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm onb-save-btn"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-outline-primary btn-sm onb-next-btn">Next <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== TAB 6: Documents & Submit ==================== -->
<div class="tab-pane fade" id="tab-documents" role="tabpanel">
    <h5 class="mb-3">12. Required Documents</h5>
    <p class="text-muted small mb-2">Ensure these PDF documents are uploaded before submitting:</p>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
            <thead class="table-light"><tr><th>Document Type</th><th>Requirement / Note</th></tr></thead>
            <tbody>
                <tr><td>Initial Bank/Card Statements</td><td>PDF copies for the requested period.</td></tr>
                <tr><td>Prior Year Tax Returns</td><td>Last two years of Federal and State filings.</td></tr>
                <tr><td>Prior Year Financials</td><td>Most recent P&amp;L and Balance Sheet.</td></tr>
                <tr><td>Employee List</td><td>Required only if wages/salaries are paid directly.</td></tr>
                <tr><td>Relative Payment Details</td><td>List of relatives and relationship types.</td></tr>
                <tr><td>Signed Auto Pay Form</td><td>Signed authorization for recurring transaction automation. <a href="<?= e(appUrl('/public/forms/auto_pay_form_blank.pdf')) ?>" target="_blank" class="text-nowrap"><i class="bi bi-download"></i> Download Blank Form</a></td></tr>
                <tr><td>W-9 Forms</td><td>One for each independent contractor.</td></tr>
            </tbody>
        </table>
    </div>

    <?php if ($canEdit && !$isEdit): ?>
    <div class="mb-3">
        <label class="form-label"><i class="bi bi-cloud-arrow-up"></i> Upload Signed Auto Pay Form (PDF)</label>
        <div class="mb-2"><a href="<?= e(appUrl('/public/forms/auto_pay_form_blank.pdf')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download Blank Auto Pay Form</a></div>
        <input type="file" name="auto_pay_form[]" class="form-control form-control-sm onb-file-input" data-section="documents_upload" multiple accept=".pdf">
        <div class="onb-file-list mt-1"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2 onb-upload-files-btn" data-section="documents_upload"><i class="bi bi-cloud-arrow-up"></i> Upload Files</button>
    </div>
    <?php endif; ?>

    <!-- Staged files list -->
    <h6 class="mt-3"><i class="bi bi-folder2-open"></i> Staged Files (will be dispatched on submit)</h6>
    <?php if ($isEdit && $isClient): ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Upload new or updated documents via the <a href="<?= e(appUrl('?action=documents')) ?>">Documents</a> section.</div>
    <?php endif; ?>
    <div id="onbStagedFilesArea">
        <div class="text-muted small" id="onbNoFiles" <?= empty($stagedFiles) ? '' : 'style="display:none"' ?>>No files staged yet.</div>
        <table class="table table-sm table-bordered mb-0" id="onbStagedTable" style="font-size:0.85rem;<?= empty($stagedFiles) ? 'display:none' : '' ?>">
            <thead class="table-light"><tr><th>#</th><th>File Name</th><th>Type</th><?php if ($canEdit && !$isEdit): ?><th style="width:2.5rem"></th><?php endif; ?></tr></thead>
            <tbody id="onbStagedBody">
            <?php foreach ($stagedFiles as $si => $sf): ?>
                <tr data-file-id="<?= e($sf['id']) ?>">
                    <td><?= $si + 1 ?></td>
                    <td><?= e($sf['original_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($sf['field']) ?></span></td>
                    <?php if ($canEdit && !$isEdit): ?>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger onb-delete-file-btn" data-file-id="<?= e($sf['id']) ?>" title="Remove"><i class="bi bi-trash"></i></button></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canEdit): ?>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary btn-sm onb-prev-btn"><i class="bi bi-arrow-left"></i> Back</button>
        <?php if (!$isEdit): ?>
        <button type="button" class="btn btn-success onb-final-submit-btn" id="onbFinalSubmitBtn">
            <i class="bi bi-send"></i> Submit Onboarding
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canReview): ?>
    <form method="POST" action="<?= e(appUrl('?action=onboarding_review')) ?>" class="mt-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Mark as Reviewed</button>
    </form>
    <?php endif; ?>
</div>

</div><!-- /tab-content -->

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
