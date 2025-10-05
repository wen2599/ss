from playwright.sync_api import sync_playwright, expect

def run_verification(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Go to the homepage and verify lottery numbers are loaded.
        page.goto("http://localhost:5173")

        # Expect the main heading to be visible.
        expect(page.get_by_role("heading", name="六合彩开奖结果")).to_be_visible()

        # Expect the lottery numbers container to eventually load and be visible.
        # This also implicitly tests the /get_numbers endpoint.
        lottery_numbers_container = page.locator(".lottery-numbers")
        expect(lottery_numbers_container).to_be_visible(timeout=10000)

        # Take a screenshot of the main page.
        page.screenshot(path="jules-scratch/verification/01_main_page_loaded.png")

        # 2. Test the registration flow.
        page.get_by_role("button", name="注册").click()

        # Fill out the registration form with a unique username.
        username = f"testuser_{page.evaluate('() => Date.now()')}"
        page.get_by_label("用户名").fill(username)
        page.get_by_label("密码").fill("password123")
        page.get_by_role("button", name="注册", exact=True).click()

        # Expect a success message.
        expect(page.get_by_text("注册成功！您现在可以登录了。")).to_be_visible()

        # The modal should close and the login modal should appear.
        # We'll wait for the login form to be visible.
        expect(page.get_by_role("heading", name="登录")).to_be_visible(timeout=5000)
        page.screenshot(path="jules-scratch/verification/02_registration_complete.png")

        # 3. Test the login flow.
        page.get_by_label("用户名").fill(username)
        page.get_by_label("密码").fill("password123")
        page.get_by_role("button", name="登录", exact=True).click()

        # Expect the welcome message for the logged-in user.
        expect(page.get_by_text(f"欢迎, {username}")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/03_login_successful.png")

        # 4. Test the logout flow.
        page.get_by_role("button", name="退出登录").click()

        # Expect the login and register buttons to reappear.
        expect(page.get_by_role("button", name="登录")).to_be_visible()
        expect(page.get_by_role("button", name="注册")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/04_logout_successful.png")

        print("Verification script completed successfully!")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")
    finally:
        browser.close()

with sync_playwright() as playwright:
    run_verification(playwright)