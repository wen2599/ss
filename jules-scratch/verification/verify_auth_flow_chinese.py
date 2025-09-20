from playwright.sync_api import sync_playwright, Page, expect

def run_verification(page: Page):
    """
    This script verifies the frontend routing and component rendering
    for the new, translated authentication flow.
    """
    base_url = "http://localhost:5173"
    page.goto(base_url)

    # Assert redirection to the login page.
    expect(page).to_have_url(f"{base_url}/login")

    # Check for the Chinese heading on the Login Page.
    login_heading = page.get_by_role("heading", name="用户登录")
    expect(login_heading).to_be_visible()

    # Navigate to the registration page.
    register_link = page.get_by_role("link", name="在此注册")
    register_link.click()

    # Assert navigation to the register page.
    expect(page).to_have_url(f"{base_url}/register")

    # Check for the Chinese heading on the Register Page.
    register_heading = page.get_by_role("heading", name="创建账户")
    expect(register_heading).to_be_visible()

    # Take a screenshot for visual verification.
    screenshot_path = "jules-scratch/verification/verification_chinese.png"
    page.screenshot(path=screenshot_path)
    print(f"Screenshot saved to {screenshot_path}")

def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        run_verification(page)
        browser.close()

if __name__ == "__main__":
    main()
