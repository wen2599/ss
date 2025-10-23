// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Authentication Flows', () => {

  test('successful registration', async ({ page }) => {
    // Mock the API response for a successful registration
    await page.route('**/api/register', route => {
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'success', data: { message: '注册成功！' } }),
      });
    });

    // Mock the API response for the destination page to prevent a real network call
    await page.route('**/api/emails', route => {
      route.fulfill({ status: 200, contentType: 'application/json', body: '[]' });
    });

    await page.goto('/register');

    // Fill in the registration form
    await page.getByLabel('邮箱').fill('test@example.com');
    await page.locator('#reg-password').fill('password123');
    await page.locator('#reg-confirm-password').fill('password123');

    // Click the register button
    await page.getByRole('button', { name: '注册' }).click();

    // Assert that the user is redirected to the homepage on successful registration
    await expect(page).toHaveURL('/');
  });

  test('registration with existing email', async ({ page }) => {
    // Mock the API response for a failed registration (email already exists)
    await page.route('**/api/register', route => {
      route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'error', message: '该邮箱已被注册。' }),
      });
    });

    await page.goto('/register');

    // Fill in the registration form
    await page.getByLabel('邮箱').fill('existing@example.com');
    await page.locator('#reg-password').fill('password123');
    await page.locator('#reg-confirm-password').fill('password123');

    // Click the register button
    await page.getByRole('button', { name: '注册' }).click();

    // Assert that the error message is displayed
    await expect(page.locator('.error-message')).toHaveText('该邮箱已被注册。');
    await expect(page).toHaveURL('/register'); // Should remain on the registration page
  });

  test('successful login', async ({ page }) => {
    // Mock the API response for a successful login
    await page.route('**/api/login', route => {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'success', data: { message: '登录成功。' } }),
      });
    });

    // Mock the API response for the destination page
    await page.route('**/api/emails', route => {
      route.fulfill({ status: 200, contentType: 'application/json', body: '[]' });
    });

    await page.goto('/login');

    // Fill in the login form
    await page.getByLabel('邮箱').fill('user@example.com');
    await page.getByLabel('密码').fill('password123');

    // Click the login button
    await page.getByRole('button', { name: '登录' }).click();

    // Assert that the user is redirected to the homepage on successful login
    await expect(page).toHaveURL('/');
  });

  test('login with incorrect credentials', async ({ page }) => {
    // Mock the API response for a failed login
    await page.route('**/api/login', route => {
      route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'error', message: '邮箱或密码错误。' }),
      });
    });

    await page.goto('/login');

    // Fill in the login form
    await page.getByLabel('邮箱').fill('wrong@example.com');
    await page.getByLabel('密码').fill('wrongpassword');

    // Click the login button
    await page.getByRole('button', { name: '登录' }).click();

    // Assert that the error message is displayed
    await expect(page.locator('.error-message')).toHaveText('邮箱或密码错误。');
    await expect(page).toHaveURL('/login'); // Should remain on the login page
  });
});
