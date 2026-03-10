const { test, expect } = require('@playwright/test');

test.describe('Admin Pages', () => {

  test.beforeEach(async ({ page }) => {
    // Log in — MU-plugin hides #wp-submit and injects #sme-submit via JS
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('admin123');
    // Wait for JS-injected #sme-submit, fall back to form.submit()
    try {
      await page.locator('#sme-submit').waitFor({ state: 'visible', timeout: 5000 });
      await page.locator('#sme-submit').click();
    } catch {
      await page.evaluate(() => document.getElementById('loginform').submit());
    }
    await page.waitForURL(/wp-admin|tenders|eprocurement/, { timeout: 30000 });
  });

  test('Dashboard loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
    const cards = page.locator('.eproc-stat-card, .eproc-card');
    expect(await cards.count()).toBeGreaterThan(0);
  });

  test('Sidebar navigation renders', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement');
    const sidebar = page.locator('.eproc-admin-sidebar, .eproc-sidebar');
    await expect(sidebar).toBeVisible();
    const navItems = sidebar.locator('.eproc-nav-item, a');
    expect(await navItems.count()).toBeGreaterThan(3);
  });

  test('Bid list page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-bids');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Bid edit page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-bids&action=new');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
    await expect(page.locator('input[name="bid_number"], #eproc-bid-number')).toBeVisible();
  });

  test('Messages page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-messages');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Contacts page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-contacts');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Bidders page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-bidders');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('SCM Documents page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-compliance');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Download log page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-downloads');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Online Bids page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-online-bids');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
    // Should have heading
    await expect(page.locator('h1')).toContainText('Online Bids');
  });

  test('Online Bids shows status filter', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-online-bids');
    const filter = page.locator('select[name="status"]');
    await expect(filter).toBeVisible();
    // Filter should have options
    const options = filter.locator('option');
    expect(await options.count()).toBeGreaterThanOrEqual(2);
  });

  test('Online Bids status filter works', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-online-bids');
    // Select "Closed" filter
    await page.selectOption('select[name="status"]', 'closed');
    await page.waitForURL(/status=closed/);
    // Page should still render without errors
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Settings page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-settings');
    await expect(page.locator('.eproc-admin-shell')).toBeVisible();
  });

  test('Settings Data Protection card visible', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-settings');
    // Scroll to bottom where Data Protection card is
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    const heading = page.getByText('Data Protection');
    await expect(heading).toBeVisible();
    const checkbox = page.locator('input[name="delete_data_on_uninstall"]');
    await expect(checkbox).toBeVisible();
    // Should be unchecked by default
    await expect(checkbox).not.toBeChecked();
  });

  test('Settings save persists Data Protection toggle', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-settings');
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

    // Check the box
    const checkbox = page.locator('input[name="delete_data_on_uninstall"]');
    await checkbox.check();
    await expect(checkbox).toBeChecked();

    // Save
    await page.locator('#eproc-save-settings').click();
    await page.waitForSelector('.eproc-notice.success', { timeout: 10000 });

    // Reload and verify persisted
    await page.reload();
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await expect(page.locator('input[name="delete_data_on_uninstall"]')).toBeChecked();

    // Uncheck and save (restore default)
    await page.locator('input[name="delete_data_on_uninstall"]').uncheck();
    await page.locator('#eproc-save-settings').click();
    await page.waitForSelector('.eproc-notice.success', { timeout: 10000 });

    // Verify unchecked persists
    await page.reload();
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await expect(page.locator('input[name="delete_data_on_uninstall"]')).not.toBeChecked();
  });

  test('Contact modal has accessibility attrs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=eprocurement-contacts');
    const modal = page.locator('#eproc-contact-modal');
    await expect(modal).toHaveAttribute('role', 'dialog');
    await expect(modal).toHaveAttribute('aria-modal', 'true');
    await expect(modal).toHaveAttribute('aria-labelledby', 'eproc-modal-title');
  });

  test('No PHP errors in admin pages', async ({ page }) => {
    const urls = [
      '/wp-admin/admin.php?page=eprocurement',
      '/wp-admin/admin.php?page=eprocurement-bids',
      '/wp-admin/admin.php?page=eprocurement-messages',
      '/wp-admin/admin.php?page=eprocurement-settings',
      '/wp-admin/admin.php?page=eprocurement-online-bids',
      '/wp-admin/admin.php?page=eprocurement-downloads',
    ];
    for (const url of urls) {
      await page.goto(url);
      const content = await page.content();
      expect(content).not.toContain('Fatal error');
      expect(content).not.toContain('Parse error');
      expect(content).not.toContain('Warning:');
    }
  });

});
