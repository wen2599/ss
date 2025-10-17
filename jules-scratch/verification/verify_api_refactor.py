from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Navigate to the login page
        page.goto("http://127.0.0.1:5173/login")

        # Fill in the login form and submit
        page.get_by_label("Email").fill("test@example.com")
        page.get_by_label("Password").fill("password")
        page.get_by_role("button", name="Login").click()

        # Wait for navigation to the home page (or bills page)
        expect(page).to_have_url("http://127.0.0.1:5173/", timeout=10000)

        # Navigate to the bills page
        page.goto("http://127.0.0.1:5173/bills")

        # Wait for the heading of the bills page to be visible
        expect(page.get_by_role("heading", name="Bills")).to_be_visible()

        # Wait for at least one bill item to be visible, indicating the API call was successful
        expect(page.locator(".bill-item")).to_have_count(1, timeout=15000)

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")
        print("Screenshot taken successfully.")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)