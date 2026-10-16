import shutil
import pathlib
from playwright.sync_api import sync_playwright

BASE = pathlib.Path(r"C:\Users\KATANA\Documents\chatbot\video-demo")
SHOTS = BASE / "shots"
SHOTS.mkdir(parents=True, exist_ok=True)
URL = "https://chatbot-universidad.vercel.app"


def shot(page, name):
    page.screenshot(path=str(SHOTS / f"{name}.png"))
    print(f"[shot] {name}.png")


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    ctx = browser.new_context(
        record_video_dir=str(BASE / "raw"),
        viewport={"width": 1100, "height": 850},
        record_video_size={"width": 1100, "height": 850},
    )
    page = ctx.new_page()
    errors = []
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on(
        "console",
        lambda m: errors.append(f"[console:{m.type}] {m.text}")
        if m.type == "error"
        else None,
    )

    print("1. Abriendo la pagina...")
    page.goto(URL, wait_until="networkidle", timeout=60000)
    page.wait_for_selector(".message-bubble.bot", timeout=30000)
    page.wait_for_timeout(9000)  # saludo + typewriter
    shot(page, "01-bienvenida")

    print("2. Click en quick reply 'Ver carreras'...")
    page.click("button.quick-reply-btn:has-text('Ver carreras')", timeout=15000)
    page.wait_for_selector(".typing-indicator", timeout=15000)
    page.wait_for_selector(".typing-indicator", state="detached", timeout=90000)
    page.wait_for_timeout(10000)
    shot(page, "02-carreras")

    print("3. Pregunta de precios...")
    page.fill("input.chat-input", "¿Cuánto cuesta la inscripción y mensualidad?")
    page.keyboard.press("Enter")
    page.wait_for_selector(".typing-indicator", timeout=15000)
    page.wait_for_selector(".typing-indicator", state="detached", timeout=90000)
    page.wait_for_timeout(17000)
    shot(page, "03-precios")

    print("4. Pregunta de horarios...")
    page.fill("input.chat-input", "¿Cuáles son los horarios generales?")
    page.keyboard.press("Enter")
    page.wait_for_selector(".typing-indicator", timeout=15000)
    page.wait_for_selector(".typing-indicator", state="detached", timeout=90000)
    page.wait_for_timeout(15000)
    shot(page, "04-horarios")

    print("5. Cerrando y guardando video...")
    vpath = page.video.path()
    ctx.close()
    browser.close()
    out = BASE / "demo-chatbot-live.webm"
    shutil.copy(vpath, out)
    print(f"VIDEO: {out}")
    print(f"ERRORES FRONTEND: {len(errors)}")
    for e in errors[:5]:
        print("  -", e)
    print("OK")