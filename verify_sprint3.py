import os
import sys
import subprocess
import time
from playwright.sync_api import sync_playwright

def start_server():
    print("Starting Laravel server...")
    # Kill any process on port 8000
    subprocess.run("kill $(lsof -t -i :8000) 2>/dev/null || true", shell=True)
    time.sleep(1)

    # Start php artisan serve in the background
    server_process = subprocess.Popen(
        "php artisan serve --port=8000",
        shell=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    time.sleep(3) # Wait for server to boot up
    return server_process

def run_e2e():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    os.makedirs("/home/jules/verification/videos", exist_ok=True)

    server = start_server()

    print("Launching Playwright browser...")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        try:
            print("Navigating to login page...")
            page.goto("http://127.0.0.1:8000/login")
            page.wait_for_timeout(1000)

            print("Logging in as admin...")
            page.fill("input[type='email']", "admin@novapos.com")
            page.fill("input[type='password']", "Password123!")

            # Click the submit button
            page.click("button")
            page.wait_for_timeout(3000)

            print("Current URL:", page.url)
            if "dashboard" not in page.url:
                print("Login failed, current URL:", page.url)
                sys.exit(1)

            print("Navigating to Categories page...")
            page.goto("http://127.0.0.1:8000/categories")
            page.wait_for_timeout(2000)

            print("Checking for Electronics category...")
            # Check if Electronics is in the table
            electronics_element = page.locator("text=Electronics")
            if electronics_element.count() > 0:
                print("Category 'Electronics' found!")
            else:
                print("Category 'Electronics' not found in table!")
                sys.exit(1)

            print("Taking screenshot...")
            page.screenshot(path="/home/jules/verification/screenshots/categories.png")
            page.wait_for_timeout(1000)

            print("E2E Test passed successfully!")

        except Exception as e:
            print("An error occurred during E2E testing:", str(e))
            sys.exit(1)
        finally:
            context.close()
            browser.close()
            # Stop the Laravel server
            subprocess.run("kill $(lsof -t -i :8000) 2>/dev/null || true", shell=True)

if __name__ == "__main__":
    run_e2e()
