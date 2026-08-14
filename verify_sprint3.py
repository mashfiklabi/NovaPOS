import os
import sys
import subprocess
import time
from playwright.sync_api import sync_playwright

def start_server():
    print("Starting Laravel server...")
    subprocess.run("kill $(lsof -t -i :8000) 2>/dev/null || true", shell=True)
    time.sleep(1)
    server_process = subprocess.Popen(
        "php artisan serve --port=8000",
        shell=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    time.sleep(3)
    return server_process

def run_e2e():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    os.makedirs("/home/jules/verification/videos", exist_ok=True)
    server = start_server()
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(record_video_dir="/home/jules/verification/videos")
        page = context.new_page()
        try:
            print("Navigating to login...")
            page.goto("http://127.0.0.1:8000/login")
            page.wait_for_timeout(1000)
            print("Logging in...")
            page.fill("input[type='email']", "admin@novapos.com")
            page.fill("input[type='password']", "Password123!")
            page.click("button")
            page.wait_for_timeout(3000)
            print("Categories...")
            page.goto("http://127.0.0.1:8000/categories")
            page.wait_for_timeout(2000)
            page.screenshot(path="/home/jules/verification/screenshots/categories.png")
            print("E2E Test passed successfully!")
        except Exception as e:
            print("Error:", str(e))
            sys.exit(1)
        finally:
            context.close()
            browser.close()
            subprocess.run("kill $(lsof -t -i :8000) 2>/dev/null || true", shell=True)

if __name__ == '__main__':
    run_e2e()
