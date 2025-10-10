import re
from playwright.sync_api import sync_playwright, expect, Page

def verify_full_redesign(page: Page):
    """
    Verifies the full UI redesign by navigating to each core page
    and taking a screenshot to confirm the new dark theme and layout.
    """
    base_url = "http://localhost:5173"

    # 1. Verify Home Page
    print("Verifying Home Page...")
    page.goto(base_url + "/")
    expect(page.get_by_role("heading", name="最新开奖")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/01-home-page.png")
    print("Screenshot for Home Page saved.")

    # 2. Verify Login Page
    print("Verifying Login Page...")
    page.get_by_role("link", name="登录").click()
    expect(page).to_have_url(re.compile(".*/login"))
    expect(page.get_by_role("heading", name="用户登录")).to_be_visible()
    page.screenshot(path="jules-scratch/verification/02-login-page.png")
    print("Screenshot for Login Page saved.")

    # 3. Verify Register Page
    print("Verifying Register Page...")
    page.get_by_role("link", name="注册").click()
    expect(page).to_have_url(re.compile(".*/register"))
    expect(page.get_by_role("heading", name="创建账户")).to_be_visible()
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
            verify_full_redesign(page)
            print("\nVerification script completed successfully!")
        except Exception as e:
            print(f"\nAn error occurred during verification: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    main()