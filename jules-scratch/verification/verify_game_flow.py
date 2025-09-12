from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Navigate to the app
        page.goto("http://localhost:5173/")

        # Click the register/login button
        page.get_by_role("button", name="注册/登录").click()

        # Register a new user
        page.get_by_placeholder("手机号").fill("1234567890")
        page.get_by_placeholder("密码").fill("password")
        page.get_by_placeholder("确认密码").fill("password")
        page.get_by_role("button", name="注册").click()

        # Wait for registration to complete and close the modal
        expect(page.get_by_text("ID:")).to_be_visible()

        # Take a screenshot of the lobby
        page.screenshot(path="jules-scratch/verification/lobby.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
