import { expect, Page, test } from '@playwright/test';

const PASSWORD = process.env.RAPTOR_DEMO_PASSWORD || 'Raptor@12345';

const users = {
  admin: process.env.RAPTOR_ADMIN_EMAIL || 'admin1@raptor.test',
  hr: process.env.RAPTOR_HR_EMAIL || 'hr1@raptor.test',
  analyst: process.env.RAPTOR_ANALYST_EMAIL || 'analyst1@raptor.test',
  manager: process.env.RAPTOR_MANAGER_EMAIL || 'manager1@raptor.test',
  employee: process.env.RAPTOR_EMPLOYEE_EMAIL || 'employee1@raptor.test'
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

async function expectNoFatalErrors(page: Page) {
  await expect(page.locator('body')).not.toContainText(/fatal error|parse error|warning:/i);
}

test.describe('Raptor CRM seeded smoke suite', () => {
  test('login screen loads and theme toggle works', async ({ page }) => {
    await page.goto('index.php?route=auth/login');
    await expect(page.getByLabel(/email/i)).toBeVisible();
    await expect(page.getByLabel(/password/i)).toBeVisible();
    await expectNoFatalErrors(page);

    const toggle = page.locator('#theme-toggle, [title*="Theme"], [title*="theme"]').first();
    if (await toggle.count()) {
      await toggle.click();
      await expect(page.locator('html')).toHaveAttribute('data-theme', /dark|light/);
    }
  });

  test('admin can access dashboards, employees, reports, and edit requests', async ({ page }) => {
    await login(page, users.admin);
    await expect(page.locator('body')).toContainText(/Dashboard Module/i);
    await page.goto('index.php?route=dashboard/index');
    await expect(page.locator('body')).toContainText(/Dashboard Module/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).toContainText(/Employee Management/i);
    await page.goto('index.php?route=reports/index');
    await expect(page.locator('body')).toContainText(/Reports/i);
    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).toContainText(/Data Edit Requests/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('HR can manage employees but cannot access admin settings', async ({ page }) => {
    await login(page, users.hr);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).toContainText(/Employee Management/i);
    await page.goto('index.php?route=settings/index');
    await expect(page.locator('body')).not.toContainText(/Global Settings/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('analyst can access dashboard templates and reports', async ({ page }) => {
    await login(page, users.analyst);
    await page.goto('index.php?route=dashboard/templates');
    await expect(page.locator('body')).toContainText(/Dashboard Templates/i);
    await page.goto('index.php?route=reports/index');
    await expect(page.locator('body')).toContainText(/Reports/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).not.toContainText(/Employee Management/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('manager can view task board and create edit request', async ({ page }) => {
    await login(page, users.manager);
    await page.goto('index.php?route=tasks/index');
    await expect(page.locator('body')).toContainText(/Task Board|Operations Task Board/i);

    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).toContainText(/Data Edit Requests/i);
    await page.locator('select[name="entity_type"]').selectOption('lead');
    await page.locator('input[name="entity_id"]').fill('1');
    await page.locator('select[name="requested_action"]').selectOption('update');
    await page.locator('input[name="manager_comment"]').fill('Automated E2E request: qualify seeded lead.');
    await page.locator('textarea[name="proposed_changes"]').fill('{"status":"qualified"}');
    await page.getByRole('button', { name: /submit request/i }).click();
    await expect(page.locator('body')).toContainText(/Automated E2E request|Data Edit Requests/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('employee has self-service operations and no admin access', async ({ page }) => {
    await login(page, users.employee);
    await page.goto('index.php?route=attendance/index');
    await expect(page.locator('body')).toContainText(/Attendance|Check/i);
    await page.goto('index.php?route=tasks/index');
    await expect(page.locator('body')).toContainText(/Task/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).not.toContainText(/Employee Management/i);
    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).not.toContainText(/Submit Request/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('delete routes are blocked by governance policy', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leads/delete/1');
    await expect(page.locator('body')).toContainText(/Deletion is disabled|governance policy/i);
    await expectNoFatalErrors(page);
  });
});
