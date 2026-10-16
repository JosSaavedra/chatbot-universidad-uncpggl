"""Genera una pista ambiental original (sin derechos) en WAV.

Estilo: pad suave + arpegio estilo piano, progresion amable (Cmaj7-Am7-Fmaj7-G6).
Solo usa la libreria estandar de Python. Salida: 44.1 kHz, estereo, 16 bits.
"""
import math
import wave
import array
import sys

SR = 44100

NOTE = {
    "C4": 261.63, "E4": 329.63, "G4": 392.00, "B4": 493.88,
    "A3": 220.00, "C5": 523.25, "E5": 659.26,
    "F4": 349.23, "A4": 440.00,
    "D4": 293.66, "G3": 196.00,
}

# Progresion: cada acorde dura DUR_CHORD segundos
CHORDS = [
    [("C4", 0.30), ("E4", 0.24), ("G4", 0.20), ("B4", 0.16)],  # Cmaj7
    [("A3", 0.30), ("C4", 0.24), ("E4", 0.20), ("G4", 0.16)],  # Am7
    [("F4", 0.26), ("A4", 0.20), ("C5", 0.18), ("E4", 0.18)],  # Fmaj7
    [("D4", 0.28), ("G3", 0.26), ("B4", 0.18), ("G4", 0.16)],  # G6
]

DUR_CHORD = 4.0
DUR_LOOP = DUR_CHORD * len(CHORDS)  # 16 s


def sine(freq, t, amp, harmonics=(1.0,)):
    """Onda seno con armonicos suaves (timbre de pad/piano)."""
    v = 0.0
    for i, h in enumerate(harmonics, start=1):
        if h == 0:
            continue
        v += amp * h * math.sin(2 * math.pi * freq * i * t)
    return v


def build(length_s, out_path, fade_in=2.5, fade_out=3.5, pad_vol=0.055, arp_vol=0.11):
    n = int(length_s * SR)
    left = [0.0] * n
    right = [0.0] * n

    chords = [c for _ in range(int(length_s / DUR_LOOP) + 1) for c in CHORDS]
    t0 = 0.0
    for idx, chord in enumerate(chords):
        t1 = t0 + DUR_CHORD
        if t0 >= length_s:
            break
        seg_start = int(t0 * SR)
        seg_end = min(int(t1 * SR), n)
        seg_len = seg_end - seg_start
        if seg_len <= 0:
            t0 = t1
            continue

        # Envolvente del pad: ataque 1.2s, liberacion 1.0s (cruza con el siguiente)
        for i in range(seg_len):
            tt = i / SR
            env_pad = min(1.0, tt / 1.2) * min(1.0, (seg_len / SR - tt) / 1.0)
            # notacion esterna sutil (LFO lento para que respire)
            lfo = 1.0 + 0.06 * math.sin(2 * math.pi * 0.08 * (t0 + tt))
            for (note, amp) in chord:
                freq = NOTE[note]
                s = sine(freq, tt, amp, harmonics=(1.0, 0.35, 0.12))
                v = s * env_pad * pad_vol * lfo
                # panorama leve por nota para dar espacio
                pan = 0.6 + 0.4 * math.sin(idx * 2.3 + amp * 7.0)
                left[seg_start + i] += v * (2 - pan) * 0.5
                right[seg_start + i] += v * pan * 0.5

        # Arpegio estilo piano: una nota del acorde por segundo
        for beat in range(int(DUR_CHORD)):
            note, amp = chord[beat % len(chord)]
            freq = NOTE[note]
            b_start = seg_start + int(beat * SR)
            b_end = min(b_start + int(2.2 * SR), seg_end)
            if b_start >= n:
                continue
            for i in range(b_start, b_end):
                tt = (i - b_start) / SR
                # decaimiento exponencial tipo piano
                env_p = math.exp(-3.2 * tt) * min(1.0, tt / 0.012)
                s = sine(freq, tt, amp, harmonics=(1.0, 0.5, 0.15))
                v = s * env_p * arp_vol
                left[i] += v
                right[i] += v

        t0 = t1

    # Normalizar y fades master
    peak = max(max(abs(x) for x in left), max(abs(x) for x in right)) or 1.0
    gain = 0.85 / peak
    fade_in_n = int(fade_in * SR)
    fade_out_n = int(fade_out * SR)

    def apply_fade(idx, total):
        if idx < fade_in_n:
            return min(1.0, idx / fade_in_n)
        if idx > total - fade_out_n:
            return max(0.0, (total - idx) / fade_out_n)
        return 1.0

    data = array.array("h")
    for i in range(n):
        f = apply_fade(i, n)
        data.append(int(max(-1.0, min(1.0, left[i] * gain * f)) * 32767))
        data.append(int(max(-1.0, min(1.0, right[i] * gain * f)) * 32767))

    with wave.open(out_path, "wb") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(data.tobytes())

    print(f"WAV generado: {out_path} ({length_s:.1f}s, {len(data)//SR} canales*2)")


if __name__ == "__main__":
    dur = float(sys.argv[1]) if len(sys.argv) > 1 else 76.0
    out = sys.argv[2] if len(sys.argv) > 2 else "music.wav"
    build(dur, out)