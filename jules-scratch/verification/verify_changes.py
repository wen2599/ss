from playwright.sync_api import sync_playwright, Page, expect

def verify_changes():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to the locally served frontend
        page.goto("http://localhost:3000")

        # 1. Verify the app name change in the title
        expect(page).to_have_title("十三张")

        # 2. Verify the full-screen layout by checking the game container
        # In the lobby view, the main container is .App, not .game-container
        # We'll just take a screenshot of the lobby, which should fill the screen.

        # Take a screenshot to visually verify the changes
        page.screenshot(path="jules-scratch/verification/verification.png")

        browser.close()

if __name__ == "__main__":
    verify_changes()
