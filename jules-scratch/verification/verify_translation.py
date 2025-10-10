import re
from playwright.sync_api import sync_playwright, expect, Page

def verify_all_pages_translation(page: Page):
    """
    Verifies the UI translation for all major pages by taking screenshots.
    """
    base_url = "http://localhost:5173"

    # 1. Verify Home Page
    print("Verifying Home Page...")
    page.goto(base_url + "/")
    # Wait for a known element to ensure the page is loaded
    expect(page.get_by_role("heading", name="最新开奖号码")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/01-home-page.png")
    print("Screenshot for Home Page saved.")

    # 2. Verify Login Page
    print("Verifying Login Page...")
    # Use the link from the navbar to navigate
    page.get_by_role("link", name="登录").click()
    expect(page).to_have_url(re.compile(".*/login"))
    expect(page.get_by_role("heading", name="登录")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/02-login-page.png")
    print("Screenshot for Login Page saved.")

    # 3. Verify Register Page
    print("Verifying Register Page...")
    page.get_by_role("link", name="注册").click()
    expect(page).to_have_url(re.compile(".*/register"))
    expect(page.get_by_role("heading", name="注册")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/03-register-page.png")
    print("Screenshot for Register Page saved.")

    # 4. Verify Bills Page
    print("Verifying Bills Page...")
    page.get_by_role("link", name="账单").click()
    expect(page).to_have_url(re.compile(".*/bills"))
    expect(page.get_by_role("heading", name="账单中心")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/04-bills-page.png")
    print("Screenshot for Bills Page saved.")


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_all_pages_translation(page)
            print("\nVerification script completed successfully!")
        except Exception as e:
            print(f"\nAn error occurred during verification: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    main()