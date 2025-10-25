from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Navigate to the login page
        page.goto("http://localhost:5173/login")

        # Fill in the login form
        page.get_by_label("邮箱地址").fill("test@example.com")
        page.get_by_label("密码").fill("password")

        # Click the login button
        page.get_by_role("button", name="登录").click()

        # Wait for navigation to the home page
        page.wait_for_url("http://localhost:5173/")

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")

        print("Verification successful. Screenshot saved to jules-scratch/verification/verification.png")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
