from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Mock the login API response
    page.route("**/api/login_user", lambda route: route.fulfill(
        status=200,
        json={"user": {"id": 1, "email": "test@example.com"}}
    ))

    # Go to the login page
    page.goto("http://localhost:5173/login")

    # Fill in the login form
    page.get_by_label("电子邮件").fill("test@example.com")
    page.get_by_label("密码").fill("password123")

    # Click the login button
    page.get_by_role("button", name="登录").click()

    # Wait for the "My Bills" heading to be visible on the bills page
    expect(page.get_by_role("heading", name="我的账单")).to_be_visible()

    # Take a screenshot of the bills page
    page.screenshot(path="jules-scratch/verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)