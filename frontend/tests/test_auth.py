import pytest
from playwright.sync_api import Page, expect

def test_register_and_login(page: Page):
    """
    Tests the full user registration and login flow.
    """
    # Listen for all console events and print them to stdout.
    page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))

    # Go to the registration page.
    page.goto("/register")

    # Generate a unique email for the test run to ensure a clean registration.
    import time
    email = f"testuser_{int(time.time())}@example.com"
    password = "password123"

    # Fill out the registration form.
    page.get_by_label("用户名：").fill("testuser") # Added for completeness, will be removed later
    page.get_by_label("邮箱：").fill(email)
    page.locator("#reg-password").fill(password)
    page.locator("#reg-confirm-password").fill(password)
    page.get_by_role("button", name="注册").click()

    # Expect a success message and wait for navigation to the login page.
    expect(page.locator(".alert.success")).to_have_text("注册成功！请登录。")
    page.wait_for_url("**/login")

    # Now, attempt to log in with the newly created credentials.
    page.get_by_label("用户名或邮箱：").fill(email)
    page.locator("#password").fill(password)
    page.get_by_role("button", name="登录").click()

    # After a successful login, expect to be redirected to the /bills page.
    expect(page).to_have_url("**/bills")
    expect(page.get_by_role("heading", name="我的账单")).to_be_visible()
