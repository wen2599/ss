from playwright.sync_api import Page, expect
import pytest

@pytest.fixture(scope="module")
def browser_context_args(browser_context_args):
    return {
        **browser_context_args,
        "ignore_https_errors": True,
    }

def test_registration_and_login(page: Page):
    """
    This test verifies that a user can register and then log in.
    """
    # 1. Arrange: Go to the registration page.
    page.goto("http://192.168.0.2:5173/register")

    # 2. Act: Fill out the registration form and submit it.
    email = "testuser@example.com"
    password = "password123"
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Register").click()

    # 3. Assert: Check that the user is redirected to the home page.
    expect(page).to_have_url("http://192.168.0.2:5173/")

    # 4. Act: Log out.
    page.get_by_role("button", name="Logout").click()

    # 5. Assert: Check that the user is redirected to the login page.
    expect(page).to_have_url("http://192.168.0.2:5173/login")

    # 6. Act: Fill out the login form and submit it.
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Login").click()

    # 7. Assert: Check that the user is redirected to the home page.
    expect(page).to_have_url("http://192.168.0.2:5173/")
