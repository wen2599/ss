import asyncio
from playwright.async_api import async_playwright, expect

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        page = await browser.new_page()

        # Mock data for the logs
        mock_logs_data = {
            "success": True,
            "data": [
                {
                    "id": 1,
                    "filename": "text_file.txt",
                    "created_at": "2024-09-17 10:00:00",
                    "parsed_data": {
                        "type": "text",
                        "data": [
                            {"content": "https://google.com"},
                            {"content": "https://github.com"}
                        ]
                    }
                }
            ]
        }

        mock_session_data = {
            "loggedIn": True,
            "user": {"id": 1, "email": "test@example.com"}
        }

        # Set up the mock routes
        await page.route("**/api/check_session.php", lambda route: route.fulfill(json=mock_session_data))
        await page.route("**/api/get_logs.php", lambda route: route.fulfill(json=mock_logs_data))

        # Navigate to the app
        await page.goto("http://localhost:5173/")

        # Wait for the initial log list to render
        await expect(page.locator("li", has_text="text_file.txt")).to_be_visible()

        # --- Click the button and debug the result ---
        text_log_row = page.locator("li", has_text="text_file.txt")
        await text_log_row.get_by_role("button", name="查看").click()

        # Wait for the re-render
        await page.wait_for_timeout(2000)

        # Print the HTML after the click
        print("--- HTML CONTENT AFTER CLICK ---")
        print(await page.content())
        print("--- END HTML CONTENT ---")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
