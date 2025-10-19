from playwright.sync_api import Page, expect

def test_lottery_page(page: Page):
    """
    This test verifies that the lottery page loads and displays lottery numbers.
    """
    # 1. Arrange: Go to the lottery page.
    page.goto("http://192.168.0.2:5173/lottery")

    # 2. Assert: Check for the presence of the numbers container.
    # This will confirm that the data has been loaded and rendered.
    expect(page.locator(".numbers-container")).to_be_visible()

    # 3. Screenshot: Capture the final result for visual verification.
    page.screenshot(path="jules-scratch/verification/verification.png")
