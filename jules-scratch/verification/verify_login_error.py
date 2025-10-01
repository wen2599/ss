from playwright.sync_api import Page, expect

def test_login_with_invalid_credentials(page: Page):
    """
    This test verifies that when a user tries to log in with invalid credentials,
    the application displays the correct error message from the backend,
    proving that the frontend-backend communication is working via the proxy.
    """
    # 1. Arrange: Go to the application's homepage.
    page.goto("http://localhost:5174/")

    # 2. Act: Open the login modal.
    login_button = page.get_by_role("button", name="登录 / 注册")
    expect(login_button).to_be_visible()
    login_button.click()

    # 3. Act: Fill in the login form with invalid credentials.
    email_input = page.get_by_label("邮箱：")
    password_input = page.get_by_label("密码：")
    submit_button = page.get_by_role("button", name="登录")

    expect(email_input).to_be_visible()
    email_input.fill("test@example.com")
    password_input.fill("wrongpassword")

    # 4. Act: Submit the form.
    submit_button.click()

    # 5. Assert: Check for the backend's error message.
    # The key is to see the "Invalid email or password." message, which comes
    # from the backend `login.php` script. This confirms the proxy is working.
    error_message = page.locator("p.error")
    expect(error_message).to_have_text("Invalid email or password.")
    expect(error_message).to_be_visible()

    # 6. Screenshot: Capture the final result for visual verification.
    page.screenshot(path="jules-scratch/verification/verification.png")