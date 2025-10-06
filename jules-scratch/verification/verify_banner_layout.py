import json
from playwright.sync_api import sync_playwright, Page, expect

def run_verification(page: Page):
    """
    Verifies the new Lottery Page banner layout by mocking API responses.
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

    # 2. Mock the lottery numbers API to provide test data for all banners
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
            "老澳21.30": None # To test the placeholder state
        }
        route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(json_response)
        )

    page.route("**/check_session", mock_session)
    page.route("**/get_numbers", mock_lottery_numbers)

    # 3. Navigate to the page
    print("Navigating to the application...")
    # I will use a file path to avoid server issues. I first need to build the frontend.
    # Since I cannot reliably run the server, I will try to build the project and open the index.html directly.
    # This is a workaround for the environment issues.
    # First, I need to find the build command in package.json. It's 'npm run build'.
    # Then I'll run it.
    # The output should be in a 'dist' folder.
    # Then I can navigate to 'file:///app/frontend/dist/index.html'

    # For now, I will stick to the server approach, as building adds another step that might fail.
    # I will restart the servers and try again.
    page.goto("http://localhost:5173/")


    # 4. Wait for the banners to be visible and take a screenshot
    print("Waiting for lottery banners to be visible...")

    # Expect the first banner to be visible
    expect(page.get_by_role("heading", name="新澳门六合彩")).to_be_visible()
    expect(page.get_by_text("第 2024123 期")).to_be_visible()

    # Expect the second banner to be visible
    expect(page.get_by_role("heading", name="香港六合彩")).to_be_visible()
    expect(page.get_by_text("第 2024101 期")).to_be_visible()

    # Expect the third banner (with no data) to be visible
    expect(page.get_by_role("heading", name="老澳21.30")).to_be_visible()
    expect(page.get_by_text("暂无最新开奖数据")).to_be_visible()

    print("Taking screenshot of the banner layout...")
    page.screenshot(path="jules-scratch/verification/verification.png")

    print("Verification script completed successfully.")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            run_verification(page)
        finally:
            browser.close()