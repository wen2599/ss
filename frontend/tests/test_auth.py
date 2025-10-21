import pytest
from playwright.sync_api import Page, expect

def test_register_and_login(page: Page):
    page.goto("http://localhost:5173/register")

    # Generate a unique email for each test run
    import time
    email = f"testuser_{int(time.time())}@example.com"
    password = "password123"

    # Register a new user
    page.get_by_label("邮箱：").fill(email)
    page.locator("#reg-password").fill(password)
    page.locator("#reg-confirm-password").fill(password)
    page.get_by_role("button", name="注册").click()

    # Expect a success message
    expect(page.locator(".alert.success")).to_have_text("注册成功！请登录。")

    # Wait for navigation to the login page
    page.wait_for_url("**/login")

    # Log in with the new user
    page.get_by_label("邮箱：").fill(email)
    page.locator("#password").fill(password)
    page.get_by_role("button", name="登录").click()

    # Expect to be redirected to the bills page
    expect(page).to_have_url("http://localhost:5173/bills")
    expect(page.get_by_role("heading", name="我的账单")).to_be_visible()
