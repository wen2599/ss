import pytest
from playwright.sync_api import Page, expect
import time
import json

# Use a unique username for each run to avoid conflicts
unique_username = f"testuser_{int(time.time())}"
password = "password123"

def test_full_user_flow_in_chinese(page: Page):
    """
    Tests the full user flow on the Chinese UI by mocking backend responses.
    """

    # --- Mock API Responses (no change needed here) ---
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
            "subject": "这是一个测试",
            "date": "Tue, 1 Jan 2025 12:00:00 +0000",
            "body": "你好，这是邮件正文。\n它有多行。"
        })
    ))

    # --- Start Test Flow ---
    page.goto("http://localhost:5173/")

    # --- 1. Registration (in Chinese) ---
    # Make the locator specific by chaining it to the "main" role
    page.get_by_role("main").get_by_role("link", name="注册").click()
    expect(page).to_have_url("http://localhost:5173/register")

    page.get_by_label("用户名").fill(unique_username)
    page.get_by_label("密码").fill(password)
    page.get_by_role("button", name="注册").click()
    expect(page.get_by_text("注册成功！正在跳转到登录页面...")).to_be_visible()
    expect(page).to_have_url("http://localhost:5173/login", timeout=5000)

    # --- 2. Login (in Chinese) ---
    page.get_by_label("用户名").fill(unique_username)
    page.get_by_label("密码").fill(password)
    page.get_by_role("button", name="登录").click()
    expect(page).to_have_url("http://localhost:5173/parser", timeout=5000)
    expect(page.get_by_text(f"欢迎, {unique_username}")).to_be_visible()

    # --- 3. Email Parsing (in Chinese) ---
    email_text = "This text doesn't matter as the response is mocked."
    parser_textarea = page.get_by_placeholder("发件人: user@example.com")
    parser_textarea.fill(email_text)
    page.get_by_role("button", name="处理邮件").click()

    # --- 4. Verification and Screenshot (in Chinese) ---
    results_container = page.locator(".results-container")
    expect(results_container).to_be_visible()

    expect(results_container.get_by_text("发件人:")).to_be_visible()
    expect(results_container.get_by_text("test@sender.com")).to_be_visible()

    expect(results_container.get_by_text("主题:")).to_be_visible()
    expect(results_container.get_by_text("这是一个测试")).to_be_visible()

    expect(results_container.get_by_text("正文:")).to_be_visible()
    expect(results_container.get_by_text("你好，这是邮件正文。")).to_be_visible()

    # Final screenshot
    page.screenshot(path="jules-scratch/verification/verification_zh.png")