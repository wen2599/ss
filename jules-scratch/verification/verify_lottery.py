from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch()
    page = browser.new_page()

    # Mock the lottery results API call
    page.route("**/api/lottery-results", lambda route: route.fulfill(
        status=200,
        json={
            "status": "success",
            "data": [
                {
                    "id": 1, "lottery_type": "老澳", "issue_number": "20240101",
                    "winning_numbers": "01,02,03,04,05,06,07",
                    "number_colors_json": "{\"01\":{\"zodiac\":\"蛇\",\"color\":\"红\"},\"02\":{\"zodiac\":\"龙\",\"color\":\"红\"},\"03\":{\"zodiac\":\"兔\",\"color\":\"蓝\"},\"04\":{\"zodiac\":\"虎\",\"color\":\"蓝\"},\"05\":{\"zodiac\":\"牛\",\"color\":\"绿\"},\"06\":{\"zodiac\":\"鼠\",\"color\":\"绿\"},\"07\":{\"zodiac\":\"猪\",\"color\":\"红\"}}",
                    "draw_date": "2024-01-01 21:30:00"
                },
                {
                    "id": 2, "lottery_type": "新澳", "issue_number": "20240101",
                    "winning_numbers": "08,09,10,11,12,13,14",
                    "number_colors_json": "{\"08\":{\"zodiac\":\"狗\",\"color\":\"红\"},\"09\":{\"zodiac\":\"鸡\",\"color\":\"蓝\"},\"10\":{\"zodiac\":\"猴\",\"color\":\"蓝\"},\"11\":{\"zodiac\":\"羊\",\"color\":\"绿\"},\"12\":{\"zodiac\":\"马\",\"color\":\"红\"},\"13\":{\"zodiac\":\"蛇\",\"color\":\"红\"},\"14\":{\"zodiac\":\"龙\",\"color\":\"蓝\"}}",
                    "draw_date": "2024-01-01 22:30:00"
                },
                {
                    "id": 3, "lottery_type": "香港", "issue_number": "20240101",
                    "winning_numbers": "15,16,17,18,19,20,21",
                    "number_colors_json": "{\"15\":{\"zodiac\":\"兔\",\"color\":\"蓝\"},\"16\":{\"zodiac\":\"虎\",\"color\":\"绿\"},\"17\":{\"zodiac\":\"牛\",\"color\":\"绿\"},\"18\":{\"zodiac\":\"鼠\",\"color\":\"红\"},\"19\":{\"zodiac\":\"猪\",\"color\":\"红\"},\"20\":{\"zodiac\":\"狗\",\"color\":\"蓝\"},\"21\":{\"zodiac\":\"鸡\",\"color\":\"绿\"}}",
                    "draw_date": "2024-01-01 23:30:00"
                }
            ]
        }
    ))

    page.goto("http://localhost:5173/lottery")

    # Use expect to wait for the element to be visible
    expect(page.locator(".lottery-banner").first).to_be_visible()

    page.screenshot(path="jules-scratch/verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
