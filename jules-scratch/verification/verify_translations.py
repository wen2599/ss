from playwright.sync_api import sync_playwright, expect

def run_verification(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Set up API mocking before navigating to the page
        def handle_route(route):
            url = route.request.url
            if "register" in url and route.request.method == "POST":
                print(f"Intercepted registration API call to: {url}")
                route.fulfill(
                    status=200,
                    content_type="application/json",
                    body='{"success": true, "message": "您的注册申请已提交，请等待管理员批准。"}'
                )
            elif "get_lottery_results" in url or "get_game_data" in url or "check_session" in url:
                print(f"Intercepted initial data API call to: {url}")
                route.fulfill(
                    status=200,
                    content_type="application/json",
                    body='{"success": true, "results": [], "colorMap": {}}'
                )
            else:
                # Let unhandled requests pass through
                route.continue_()

        # Apply a single, robust routing rule before any page action
        page.route("**/*", handle_route)

        # 2. Navigate to the app
        page.goto("http://localhost:5173")

        # 3. Verify Navbar Title
        navbar_brand = page.get_by_role("link", name="彩票助手")
        expect(navbar_brand).to_be_visible()
        print("Verification successful: Navbar title is '彩票助手'.")

        # 4. Open Auth Modal and switch to register
        login_register_button = page.get_by_role("button", name="登录 / 注册")
        login_register_button.click()

        register_toggle_button = page.get_by_role("button", name="立即注册")
        register_toggle_button.click()

        # 5. Fill out and submit registration form
        email_input = page.get_by_label("邮箱：")
        password_input = page.get_by_label("密码：")

        email_input.fill("testuser.mock@example.com")
        password_input.fill("password123")

        register_button = page.get_by_role("button", name="注册").nth(1) # There are two "注册" buttons
        register_button.click()

        # 5. Verify the new success message
        success_message = page.get_by_text("注册申请已提交，请等待管理员批准。")
        expect(success_message).to_be_visible()
        print("Verification successful: Registration success message is correct.")

        # 5. Take a screenshot for final confirmation
        page.screenshot(path="jules-scratch/verification/verification.png")
        print("Screenshot 'jules-scratch/verification/verification.png' captured.")

    except Exception as e:
        print(f"An error occurred during verification: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run_verification(playwright)