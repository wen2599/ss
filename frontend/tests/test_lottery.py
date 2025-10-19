from playwright.sync_api import Page, expect
import pytest

@pytest.fixture(scope="module")
def browser_context_args(browser_context_args):
    return {
        **browser_context_args,
        "ignore_https_errors": True,
    }

def test_lottery_page(page: Page):
    """
    This test verifies that the lottery page loads and displays lottery numbers.
    """
    # 1. Arrange: Log in.
    page.goto("http://192.168.0.2:5173/login")
    email = "testuser@example.com"
    password = "password123"
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Login").click()
    expect(page).to_have_url("http://192.168.0.2:5173/")

    # 2. Act: Go to the lottery page.
    page.get_by_role("link", name="Lottery").click()

    # 3. Assert: Check for the presence of the numbers container.
    expect(page.locator(".numbers-container")).to_be_visible()
