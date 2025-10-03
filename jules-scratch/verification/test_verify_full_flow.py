import pytest
from playwright.sync_api import Page, expect
import time
import json

# Use a unique username for each run to avoid conflicts
unique_username = f"testuser_{int(time.time())}"
password = "password123"

def test_full_user_flow_with_mocking(page: Page):
    """
    Tests the full user flow by mocking backend responses.
    This allows frontend verification without a live database.
    """

    # --- Mock API Responses ---
    page.route("**/register", lambda route: route.fulfill(
        status=201,
        content_type="application/json",
        body=json.dumps({"message": "User registered successfully."})
    ))
    page.route("**/login", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body=json.dumps({
            "message": "Login successful.",
            "user": {"id": 1, "username": unique_username, "is_admin": False}
        })
    ))
    page.route("**/process_email", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body=json.dumps({
            "from": "test@sender.com",
            "to": "N/A",
            "subject": "This is a test",
            "date": "Tue, 1 Jan 2025 12:00:00 +0000",
            "body": "Hello, this is the email body.\nIt has multiple lines."
        })
    ))

    # --- Start Test Flow ---
    page.goto("http://localhost:5173/")

    # --- 1. Registration ---
    page.get_by_role("link", name="Sign Up").click()
    expect(page).to_have_url("http://localhost:5173/register")
    page.get_by_label("Username").fill(unique_username)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Register").click()
    expect(page.get_by_text("Registration successful!")).to_be_visible()
    expect(page).to_have_url("http://localhost:5173/login", timeout=5000)

    # --- 2. Login ---
    page.get_by_label("Username").fill(unique_username)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Login").click()
    expect(page).to_have_url("http://localhost:5173/parser", timeout=5000)
    expect(page.get_by_text(f"Welcome, {unique_username}")).to_be_visible()

    # --- 3. Email Parsing ---
    email_text = "This text doesn't matter as the response is mocked."
    parser_textarea = page.get_by_placeholder("From: user@example.com")
    parser_textarea.fill(email_text)
    page.get_by_role("button", name="Process Email").click()

    # --- 4. Verification and Screenshot ---
    results_container = page.locator(".results-container")
    expect(results_container).to_be_visible()

    # Use more granular and robust assertions
    expect(results_container.get_by_text("From:")).to_be_visible()
    expect(results_container.get_by_text("test@sender.com")).to_be_visible()

    expect(results_container.get_by_text("Subject:")).to_be_visible()
    expect(results_container.get_by_text("This is a test")).to_be_visible()

    expect(results_container.get_by_text("Body:")).to_be_visible()
    expect(results_container.get_by_text("Hello, this is the email body.")).to_be_visible()

    # Final screenshot
    page.screenshot(path="jules-scratch/verification/verification.png")