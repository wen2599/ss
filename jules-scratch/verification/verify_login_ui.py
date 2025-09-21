import re
from playwright.sync_api import sync_playwright, Page, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    """
    This test verifies that the login and registration forms can be
    made visible by clicking the navigation buttons.
    """
    # 1. Navigate to the app.
    page.goto("http://localhost:5173/")

    # 2. Take a screenshot of the main page.
    page.screenshot(path="jules-scratch/verification/01_main_page.png")

    # 3. Click the "Login" button and verify the form appears.
    login_button = page.get_by_role("button", name="登录")
    login_button.click()

    # Wait for the login form heading to be visible
    expect(page.get_by_role("heading", name="登录")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/02_login_form.png")

    # 4. Click the "Register" button and verify the form appears.
    register_button = page.get_by_role("button", name="注册")
    register_button.click()

    # Wait for the registration form heading to be visible
    expect(page.get_by_role("heading", name="注册")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/03_register_form.png")

    # 5. Click the title to go back to the main view
    title = page.get_by_role("heading", name="邮件文本处理器")
    title.click()

    # Wait for the main form to be visible again
    expect(page.get_by_placeholder("在此处粘贴邮件文本...")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/04_main_page_after_nav.png")

    context.close()
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
