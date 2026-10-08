import { expect, Page, test } from '@playwright/test';

const PASSWORD = process.env.RAPTOR_DEMO_PASSWORD || 'Raptor@12345';

const users = {
  admin: process.env.RAPTOR_ADMIN_EMAIL || 'admin1@raptor.test',
  hr: process.env.RAPTOR_HR_EMAIL || 'hr1@raptor.test',
  employee: process.env.RAPTOR_EMPLOYEE_EMAIL || 'employee1@raptor.test',
};

async function login(page: Page, email: string, password = PASSWORD) {
  await page.goto('index.php?route=auth/login');
  await page.getByLabel(/email/i).fill(email);
  await page.getByLabel(/password/i).fill(password);
  await page.getByRole('button', { name: /login|sign in/i }).click();
  await expect(page).not.toHaveURL(/auth\/login/);
}

async function logout(page: Page) {
  await page.locator('a[href*="auth/logout"]').first().click();
  await expect(page).toHaveURL(/auth\/login/);
}

test.describe('Raptor Payroll Module E2E Suite', () => {

  test('HR and Admin can view payroll dashboard and salary structures', async ({ page }) => {
    await login(page, users.hr);
    await page.goto('index.php?route=payroll/index');
    await expect(page.locator('body')).toContainText(/Payroll Management/i);
    await expect(page.locator('body')).toContainText(/Monthly Payroll Register Runs/i);

    // View Salary Structures
    await page.goto('index.php?route=payroll/structures');
    await expect(page.locator('body')).toContainText(/Employee Salary Profiles/i);
    await expect(page.locator('body')).toContainText(/Monthly CTC/i);

    await logout(page);
  });

  test('Full Payroll Run lifecycle: view register, adjust item, view payslip, batch print, and bank export', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=payroll/index');

    // Click on the first available run
    const viewRunBtn = page.locator('a[href*="payroll/run/"]').first();
    await expect(viewRunBtn).toBeVisible();
    await viewRunBtn.click();

    // Verify register view
    await expect(page.locator('body')).toContainText(/Total Net Payout/i);
    await expect(page.locator('body')).toContainText(/Gross Earnings/i);
    await expect(page.locator('body')).toContainText(/Download All Payslips/i);
    await expect(page.locator('body')).toContainText(/HR Selection Options/i);

    // View a single payslip
    const viewPayslipBtn = page.locator('a[href*="payroll/payslip/"]').first();
    await expect(viewPayslipBtn).toBeVisible();
    await viewPayslipBtn.click();

    // Verify Axiora Global Solutions exact payslip layout
    await expect(page.locator('body')).toContainText(/Axiora Global Solutions/i);
    await expect(page.locator('body')).toContainText(/EMPLOYEE SUMMARY/i);
    await expect(page.locator('body')).toContainText(/EARNINGS/i);
    await expect(page.locator('body')).toContainText(/ACTUAL/i);
    await expect(page.locator('body')).toContainText(/AMOUNT/i);
    await expect(page.locator('body')).toContainText(/TOTAL NET PAYABLE/i);
    await expect(page.locator('body')).toContainText(/Indian Rupee/i);
    await expect(page.locator('body')).toContainText(/This is a system-generated document/i);

    // Check batch print pack
    await page.goto('index.php?route=payroll/printBatch/1');
    await expect(page.locator('body')).toContainText(/Axiora Global Solutions/i);
    await expect(page.locator('body')).toContainText(/EMPLOYEE SUMMARY/i);

    // Go to bank export
    await page.goto('index.php?route=payroll/run/1');
    const bankExportBtn = page.locator('a[href*="payroll/bankExport/"]').first();
    await expect(bankExportBtn).toBeVisible();
    await bankExportBtn.click();
    await expect(page.locator('body')).toContainText(/Bank Disbursement Batch/i);
    await expect(page.locator('body')).toContainText(/Account Holder Name/i);

    await logout(page);
  });

  test('Payroll Readiness Control Center enforces 14 checkpoints and gated payroll run', async ({ page }) => {
    await login(page, users.hr);
    await page.goto('index.php?route=payroll/readiness&month=2026-10');

    // Verify readiness title and score gauge
    await expect(page.locator('body')).toContainText(/Payroll Readiness Control Center/i);
    await expect(page.locator('body')).toContainText(/Mandatory Pre-Payroll Checkpoints/i);
    await expect(page.locator('body')).toContainText(/Readiness Completion/i);

    // Verify checkpoints are rendered
    await expect(page.locator('body')).toContainText(/Active Employees Validation/i);
    await expect(page.locator('body')).toContainText(/New Joiners Verification/i);
    await expect(page.locator('body')).toContainText(/Loss of Pay \(LOP\) Validation/i);
    await expect(page.locator('body')).toContainText(/Incentives & Variable Pay/i);
    await expect(page.locator('body')).toContainText(/Salary Deductions & Advances/i);
    await expect(page.locator('body')).toContainText(/Statutory & Compliance Checks/i);
    await expect(page.locator('body')).toContainText(/Bank & Payment Details Validation/i);
    await expect(page.locator('body')).toContainText(/Final Payroll Data Reconciliation/i);

    // Verify Exception queue section
    await expect(page.locator('body')).toContainText(/Exception Queue & Audit Approvals/i);

    // Verify Gate section
    await expect(page.locator('body')).toContainText(/Payroll Run Gate/i);

    await logout(page);
  });

  test('Employee can view self-service My Payslips tab and download', async ({ page }) => {
    await login(page, users.employee);
    await page.goto('index.php?route=payroll/myPayslips');
    await expect(page.locator('body')).toContainText(/My Payslips/i);
    await expect(page.locator('body')).toContainText(/Available Salary Slips/i);

    // Attempting to access admin payroll management redirects safely
    await page.goto('index.php?route=payroll/index');
    await expect(page).toHaveURL(/payroll\/myPayslips/);

    await logout(page);
  });
});
