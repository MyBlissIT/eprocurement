const { test, expect } = require('@playwright/test');

test.describe('Frontend Pages', () => {

  test('Tender listing page loads', async ({ page }) => {
    await page.goto('/tenders/');
    await expect(page).toHaveTitle(/tender|eprocurement/i);
    await expect(page.locator('.eproc-wrap')).toBeVisible();
    // Hero section
    await expect(page.locator('.eproc-hero, .eproc-listing-hero')).toBeVisible();
  });

  test('Tender listing has cards or table', async ({ page }) => {
    await page.goto('/tenders/');
    const cards = page.locator('.eproc-card, .eproc-tender-card, .eproc-bid-card');
    const table = page.locator('.eproc-table, table');
    const either = await cards.count() + await table.count();
    expect(either).toBeGreaterThan(0);
  });

  test('Login page loads', async ({ page }) => {
    await page.goto('/tenders/login/');
    await expect(page.locator('.eproc-wrap')).toBeVisible();
    await expect(page.locator('input[name="log"], input[name="user_login"], #eproc-login-email')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
  });

  test('Register page loads', async ({ page }) => {
    await page.goto('/tenders/register/');
    await expect(page.locator('.eproc-wrap')).toBeVisible();
    await expect(page.locator('input[name="email"], #eproc-reg-email')).toBeVisible();
    await expect(page.locator('input[name="company_name"], #eproc-reg-company')).toBeVisible();
  });

  test('Compliance docs page loads', async ({ page }) => {
    await page.goto('/tenders/compliance/');
    await expect(page.locator('.eproc-wrap')).toBeVisible();
  });

  test('Bid detail page loads (if bids exist)', async ({ page }) => {
    await page.goto('/tenders/');
    const bidLink = page.locator('a[href*="/tenders/bid/"]').first();
    if (await bidLink.count() > 0) {
      await bidLink.click();
      await expect(page.locator('.eproc-wrap')).toBeVisible();
      await expect(page.locator('h1')).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Bid Documents' })).toBeVisible();
    }
  });

  test('Navbar is visible and has links', async ({ page }) => {
    await page.goto('/tenders/');
    const nav = page.locator('.eproc-navbar, .eproc-nav');
    await expect(nav).toBeVisible();
    const links = nav.locator('a');
    expect(await links.count()).toBeGreaterThan(0);
  });

  test('Mobile: hamburger menu toggles', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'Mobile only');
    await page.goto('/tenders/');
    const toggle = page.locator('.eproc-nav-toggle, .eproc-hamburger');
    if (await toggle.count() > 0) {
      await toggle.click();
      const navLinks = page.locator('.eproc-nav-links');
      await expect(navLinks).toBeVisible();
    }
  });

});

test.describe('Frontend Manage Pages', () => {

  test.beforeEach(async ({ page }) => {
    // Log in as admin to access manage panel
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('admin123');
    try {
      await page.locator('#sme-submit').waitFor({ state: 'visible', timeout: 5000 });
      await page.locator('#sme-submit').click();
    } catch {
      await page.evaluate(() => document.getElementById('loginform').submit());
    }
    await page.waitForURL(/wp-admin|tenders|eprocurement/, { timeout: 30000 });
  });

  test('Frontend manage Online Bids page loads', async ({ page }) => {
    await page.goto('/tenders/manage/online-bids/');
    await expect(page.locator('.eproc-manage-shell, .eproc-admin-shell')).toBeVisible();
    await expect(page.locator('h1')).toContainText('Online Bids');
  });

  test('Frontend manage Online Bids has status filter', async ({ page }) => {
    await page.goto('/tenders/manage/online-bids/');
    const filter = page.locator('select[name="status"]');
    await expect(filter).toBeVisible();
  });

  test('Frontend manage Online Bids status filter works', async ({ page }) => {
    await page.goto('/tenders/manage/online-bids/');
    await page.selectOption('select[name="status"]', 'closed');
    await page.waitForURL(/status=closed/);
    await expect(page.locator('.eproc-manage-shell, .eproc-admin-shell')).toBeVisible();
  });

  test('No PHP errors in frontend manage pages', async ({ page }) => {
    const urls = [
      '/tenders/manage/',
      '/tenders/manage/online-bids/',
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
