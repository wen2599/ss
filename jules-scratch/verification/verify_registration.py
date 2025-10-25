from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    page.goto("http://localhost:5173/")

    # Open the registration modal
    page.get_by_role("button", name="注册").click()

    # Fill out the registration form
    page.get_by_label("邮箱").fill("testuser@example.com")
    page.get_by_label("密码").fill("password123")

    # Click the register button
    page.get_by_role("button", name="注册").click()

    # Wait for the main page to show the authenticated user's email
    page.wait_for_selector("text=欢迎, testuser@example.com")

    # Take a screenshot
    page.screenshot(path="jules-scratch/verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
