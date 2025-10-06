import json
from playwright.sync_api import sync_playwright, Page, expect

def run_verification(page: Page):
    """
    Verifies the Lottery Page by mocking API responses.
    """
    # 1. Mock the session check to simulate a logged-in user
    def mock_session(route):
        json_response = {
            "loggedin": True,
            "user": {"email": "testuser@example.com"}
        }
        route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(json_response)
        )

    # 2. Mock the lottery numbers API to provide test data
    def mock_lottery_numbers(route):
        json_response = {
            "新澳门六合彩": {
                "lottery_type": "新澳门六合彩",
                "issue": "2024123",
                "numbers": ["10", "21", "32", "43", "04", "15", "26"],
                "zodiacs": ["牛", "虎", "兔", "龙", "蛇", "马", "羊"],
                "colors": ["red", "blue", "green", "red", "blue", "green", "red"]
            },
            "香港六合彩": {
                "lottery_type": "香港六合彩",
                "issue": "2024101",
                "numbers": ["01", "12", "23", "34", "45", "06", "17"],
                "zodiacs": ["猴", "鸡", "狗", "猪", "鼠", "牛", "虎"],
                "colors": ["green", "red", "blue", "green", "red", "blue", "green"]
            },
            "老澳21.30": None
        }
        route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(json_response)
        )

    page.route("**/check_session", mock_session)
    page.route("**/get_numbers", mock_lottery_numbers)

    # 3. Navigate to the page and take a screenshot
    print("Navigating to the application...")
    page.goto("http://localhost:5173/")

    # Wait for the main content to be visible, indicating login was successful
    expect(page.locator("main")).to_be_visible()

    # Wait for the lottery page title to be visible
    expect(page.get_by_role("heading", name="开奖结果")).to_be_visible()

    # Wait for the tabs to render
    expect(page.get_by_role("button", name="新澳门六合彩")).to_be_visible()
    expect(page.get_by_role("button", name="香港六合彩")).to_be_visible()

    print("Taking screenshot of the default tab (新澳门六合彩)...")
    page.screenshot(path="jules-scratch/verification/verification-tab1.png")

    # 4. Click the second tab and take another screenshot
    print("Clicking on the '香港六合彩' tab...")
    page.get_by_role("button", name="香港六合彩").click()

    # Wait for the content of the second tab to be visible
    expect(page.get_by_role("heading", name="香港六合彩")).to_be_visible()
    expect(page.get_by_text("第 2024101 期")).to_be_visible()

    print("Taking screenshot of the second tab (香港六合彩)...")
    page.screenshot(path="jules-scratch/verification/verification-tab2.png")

    # 5. Click the third tab and take another screenshot
    print("Clicking on the '老澳21.30' tab...")
    page.get_by_role("button", name="老澳21.30").click()

    # Wait for the placeholder to be visible
    expect(page.get_by_role("heading", name="等待开奖")).to_be_visible()

    print("Taking screenshot of the empty tab (老澳21.30)...")
    page.screenshot(path="jules-scratch/verification/verification-tab3.png")

    print("Verification script completed successfully.")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            run_verification(page)
        finally:
            browser.close()