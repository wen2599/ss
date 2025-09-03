import os
from playwright.sync_api import sync_playwright, expect, Page

def run_verification(page: Page):
    """
    Navigates to the built React app and takes a screenshot of the game board.
    """
    # Get the absolute path to the index.html file
    # The script is in jules-scratch/verification, the file is in frontend/build
    base_path = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
    file_path = f"file://{base_path}/frontend/build/index.html"

    print(f"Navigating to {file_path}")
    page.goto(file_path)

    # Wait for the game board to be visible
    game_board_locator = page.locator(".game-board")
    expect(game_board_locator).to_be_visible(timeout=10000) # Increased timeout for local file loading

    # Optional: Wait for cards to render to ensure the UI is fully loaded
    # Break down assertions to find the issue.

    # Expect 54 cards in player hands
    expect(page.locator(".player-hand .card")).to_have_count(54, timeout=5000)

    # Expect 5 cards in the discard pile
    expect(page.locator("[data-testid='discard-card']")).to_have_count(5, timeout=5000)

    # Expect 3 cards in the bottom cards display
    expect(page.locator(".bottom-cards .card")).to_have_count(3, timeout=5000)

    print("Game board is visible. Taking screenshot...")

    # Take a screenshot of the entire page
    screenshot_path = os.path.join(os.path.dirname(__file__), 'screenshot.png')
    page.screenshot(path=screenshot_path, full_page=True)

    print(f"Screenshot saved to {screenshot_path}")

def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        run_verification(page)
        browser.close()

if __name__ == "__main__":
    main()
