"""Mezcla el video grabado con la pista musical de fondo -> MP4."""
import re
import subprocess
from imageio_ffmpeg import get_ffmpeg_exe
import generate_music

BASE = r"C:\Users\KATANA\Documents\chatbot\video-demo"
VIDEO = BASE + r"\demo-chatbot-live.webm"
WAV = BASE + r"\music.wav"
OUT = BASE + r"\demo-chatbot-con-musica.mp4"
EXE = get_ffmpeg_exe()


def duration_of(path):
    r = subprocess.run([EXE, "-i", path], capture_output=True, text=True)
    m = re.search(r"Duration: (\d+):(\d+):(\d+(?:\.\d+)?)", r.stderr)
    if not m:
        raise RuntimeError("No se pudo leer la duracion:\n" + r.stderr[-1500:])
    h, mm, s = m.groups()
    return int(h) * 3600 + int(mm) * 60 + float(s)


print("1. Midendo duracion del video...")
dur = duration_of(VIDEO)
print(f"   Duracion: {dur:.2f}s")

print("2. Generando musica de fondo...")
generate_music.build(dur, WAV)

print("3. Mezclando video + musica -> MP4...")
cmd = [
    EXE, "-y",
    "-i", VIDEO,
    "-i", WAV,
    "-map", "0:v:0",
    "-map", "1:a:0",
    "-c:v", "libx264", "-preset", "medium", "-crf", "23",
    "-c:a", "aac", "-b:a", "160k",
    "-movflags", "+faststart",
    "-shortest",
    OUT,
]
r = subprocess.run(cmd, capture_output=True, text=True)
if r.returncode != 0:
    print("ERROR ffmpeg:\n" + r.stderr[-2000:])
    raise SystemExit(1)

print("4. Verificando resultado...")
info = subprocess.run([EXE, "-i", OUT], capture_output=True, text=True).stderr
print(info[-900:])
print("LISTO:", OUT)