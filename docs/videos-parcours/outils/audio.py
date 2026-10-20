"""Instruments, bruitages et mixage des maquettes motion PressLink.

Tout est synthétisé note à note (aucun échantillon externe) : chaque maquette
compose sa musique dans son propre son.py avec ces briques, puis appelle
mixer() qui place les voix, baisse la musique sous la parole et normalise
le tout à -14 LUFS.
"""
import json
import subprocess

import numpy as np
from scipy.io import wavfile
from scipy.signal import butter, fftconvolve, sosfilt

SR = 48_000
rng = np.random.default_rng(11)


# ---------------------------------------------------------------- outils
def hz(midi: float) -> float:
    return 440.0 * 2 ** ((midi - 69) / 12)


def piste(duree: float) -> np.ndarray:
    return np.zeros((int(duree * SR) + 2 * SR, 2))


def poser(dest: np.ndarray, son: np.ndarray, t: float, gain: float = 1.0, pan: float = 0.0) -> None:
    if t < 0:
        son, t = son[int(-t * SR):], 0
    if son.ndim == 1:
        g = np.array([np.cos((pan + 1) * np.pi / 4), np.sin((pan + 1) * np.pi / 4)]) * np.sqrt(2)
        son = son[:, None] * g[None, :]
    i = int(t * SR)
    if i >= len(dest):
        return
    j = min(len(dest), i + len(son))
    dest[i:j] += son[: j - i] * gain


def env(n: int, attaque: float, decroissance: float) -> np.ndarray:
    t = np.arange(n) / SR
    return np.clip(t / max(attaque, 1e-4), 0, 1) * np.exp(-t / decroissance)


def bruit(n: int) -> np.ndarray:
    return rng.normal(0, 1, n)


def filtre(x: np.ndarray, bas: float | None = None, haut: float | None = None, ordre: int = 2) -> np.ndarray:
    if bas and haut:
        sos = butter(ordre, [bas, haut], btype='bandpass', fs=SR, output='sos')
    elif bas:
        sos = butter(ordre, bas, btype='highpass', fs=SR, output='sos')
    else:
        sos = butter(ordre, haut, btype='lowpass', fs=SR, output='sos')
    return sosfilt(sos, x)


def glisse(f0: float, f1: float, duree: float, courbe: float = 1.0) -> np.ndarray:
    """Phase d'une fréquence qui glisse de f0 à f1."""
    n = int(duree * SR)
    x = np.linspace(0, 1, n) ** courbe
    f = f0 * (f1 / f0) ** x
    return 2 * np.pi * np.cumsum(f) / SR


def reverb(stereo: np.ndarray, temps: float = 1.5, mouille: float = 0.2) -> np.ndarray:
    n = int(temps * SR)
    t = np.arange(n) / SR
    ir = []
    for _ in range(2):
        x = filtre(bruit(n), haut=5500, ordre=1) * np.exp(-t / (temps / 4.5))
        x[: int(0.015 * SR)] = 0
        ir.append(x / np.sqrt(np.sum(x ** 2)))
    wet = np.stack([fftconvolve(stereo[:, c], ir[c])[: len(stereo)] for c in range(2)], axis=1)
    return stereo + wet * mouille


# ---------------------------------------------------------------- instruments
def kalimba(midi: float, vel: float = 1.0, duree: float = 2.0) -> np.ndarray:
    n = int(duree * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    s = np.sin(2 * np.pi * f * t) * env(n, 0.002, 0.7)
    s += 0.35 * np.sin(2 * np.pi * f * 2 * t + 0.3) * env(n, 0.002, 0.25)
    s += 0.2 * np.sin(2 * np.pi * f * 5.93 * t) * env(n, 0.0005, 0.04)
    s += 0.15 * np.sin(np.pi * f * t) * env(n, 0.004, 0.18)
    return s * vel


def marimba(midi: float, vel: float = 1.0) -> np.ndarray:
    n = int(0.9 * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    s = np.sin(2 * np.pi * f * t) * env(n, 0.001, 0.32)
    s += 0.5 * np.sin(2 * np.pi * f * 3.93 * t) * env(n, 0.0005, 0.035)
    s += 0.08 * bruit(n) * env(n, 0.0002, 0.003)
    return s * vel


def pluck(midi: float, duree: float = 0.6, vel: float = 1.0, brillance: float = 0.5) -> np.ndarray:
    """Corde pincée (Karplus-Strong) : guitare afro, pizzicato."""
    f = hz(midi)
    p = max(2, int(SR / f))
    n = int(duree * SR)
    buf = bruit(p) * 0.6
    buf = filtre(buf, haut=min(18000, 1500 + 9000 * brillance), ordre=1)
    out = np.zeros(n)
    for i in range(n):
        out[i] = buf[i % p]
        buf[i % p] = 0.5 * (buf[i % p] + buf[(i + 1) % p]) * 0.996
    return out * env(n, 0.001, duree * 0.45) * vel


def basse(midi: float, duree: float, vel: float = 1.0) -> np.ndarray:
    n = int((duree + 0.2) * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    e = np.clip(t / 0.006, 0, 1) * np.where(t < duree, 1.0, np.exp(-(t - duree) / 0.05)) * np.exp(-t / 1.2)
    s = np.sin(2 * np.pi * f * t) + 0.3 * np.sin(4 * np.pi * f * t) + 0.08 * np.sin(6 * np.pi * f * t)
    return np.tanh(1.5 * s) / 1.5 * e * vel


def log_drum(midi: float, vel: float = 1.0) -> np.ndarray:
    """Le « log drum » de l'amapiano / afro-house : basse ronde qui glisse."""
    n = int(0.7 * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    ph = 2 * np.pi * np.cumsum(f * (1 + 0.6 * np.exp(-t / 0.03))) / SR
    s = np.sin(ph) + 0.25 * np.sin(2 * ph) * np.exp(-t / 0.05)
    return np.tanh(1.8 * s) * env(n, 0.002, 0.22) * vel


def grosse_caisse(vel: float = 1.0, profond: bool = False) -> np.ndarray:
    n = int(0.5 * SR)
    t = np.arange(n) / SR
    f = (42 if profond else 50) + 90 * np.exp(-t / 0.03)
    s = np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.001, 0.2 if profond else 0.15)
    s += 0.25 * filtre(bruit(n), 2000, 9000) * env(n, 0.0005, 0.004)
    return s * vel


def charleston(ouvert: bool = False, vel: float = 1.0) -> np.ndarray:
    n = int((0.25 if ouvert else 0.06) * SR)
    return filtre(bruit(n), bas=7000, ordre=4) * env(n, 0.001, 0.08 if ouvert else 0.015) * vel


def shaker(vel: float = 1.0) -> np.ndarray:
    n = int(0.09 * SR)
    return filtre(bruit(n), 5000, 11000, 4) * env(n, 0.006, 0.025) * vel


def clap(vel: float = 1.0) -> np.ndarray:
    n = int(0.25 * SR)
    s = np.zeros(n)
    for k, d in enumerate((0, 0.009, 0.018, 0.028)):
        i = int(d * SR)
        s[i:] += filtre(bruit(n - i), 900, 5000) * env(n - i, 0.0005, 0.012 if k < 3 else 0.09)
    return s * vel


def tambour(f: float, vel: float = 1.0) -> np.ndarray:
    n = int(0.35 * SR)
    t = np.arange(n) / SR
    ton = np.sin(2 * np.pi * f * (1 + 0.25 * np.exp(-t / 0.02)) * t) * env(n, 0.001, 0.09)
    return (ton + 0.5 * filtre(bruit(n), f * 1.5, f * 6) * env(n, 0.0005, 0.02)) * vel


def nappe(notes: list[float], duree: float, vel: float = 1.0, clarte: float = 1400) -> np.ndarray:
    n = int((duree + 0.8) * SR)
    t = np.arange(n) / SR
    s = np.zeros(n)
    for m in notes:
        for d in (-0.07, 0.07):
            f = hz(m + d)
            for h in range(1, 7):
                s += np.sin(2 * np.pi * f * h * t + h) / h ** 1.3
    s = filtre(s, haut=clarte)
    e = np.clip(t / 0.4, 0, 1) * np.where(t < duree, 1.0, np.exp(-(t - duree) / 0.3))
    return s / (len(notes) * 4) * e * vel


def stab(notes: list[float], vel: float = 1.0) -> np.ndarray:
    """Accord court et brillant (house)."""
    n = int(0.45 * SR)
    t = np.arange(n) / SR
    s = np.zeros(n)
    for m in notes:
        for d in (-0.1, 0.1):
            ph = 2 * np.pi * hz(m + d) * t
            s += (2 * ((ph / (2 * np.pi)) % 1) - 1)  # dent de scie
    coupure = 600 + 5000 * np.exp(-t / 0.06)
    out = np.zeros(n)
    for i in range(0, n, 512):  # filtre qui se referme
        out[i:i + 512] = filtre(s[max(0, i - 1024):i + 512], haut=float(coupure[i]))[-len(out[i:i + 512]):]
    return out / len(notes) * env(n, 0.002, 0.12) * vel


# ---------------------------------------------------------------- bruitages
def souffle(duree: float = 0.6, vers_le_haut: bool = False) -> np.ndarray:
    n = int(duree * SR)
    b = bruit(n)
    out = np.zeros(n)
    for i in range(0, n, 256):
        x = i / n
        c = (300 + 4500 * x ** 1.6) if vers_le_haut else (300 + 3400 * np.sin(np.pi * x) ** 1.5)
        out[i:i + 256] = filtre(b[max(0, i - 512):i + 256], c * 0.6, min(c * 1.7, 20000))[-len(out[i:i + 256]):]
    e = np.linspace(0, 1, n) ** 2 if vers_le_haut else np.sin(np.pi * np.linspace(0, 1, n)) ** 2
    return out * e


def impact() -> np.ndarray:
    n = int(1.6 * SR)
    t = np.arange(n) / SR
    s = np.sin(2 * np.pi * np.cumsum(38 + 80 * np.exp(-t / 0.05)) / SR) * env(n, 0.001, 0.5)
    s += 0.5 * filtre(bruit(n), 200, 4000) * env(n, 0.001, 0.08)
    return np.tanh(1.5 * s)


def pop(aigu: float = 1.0) -> np.ndarray:
    n = int(0.08 * SR)
    t = np.arange(n) / SR
    f = (520 + 600 * np.exp(-t / 0.012)) * aigu
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.0005, 0.02)


def clic() -> np.ndarray:
    n = int(0.03 * SR)
    return filtre(bruit(n), 2000, 8000) * env(n, 0.0002, 0.004) + 0.4 * np.sin(2 * np.pi * 1800 * np.arange(n) / SR) * env(n, 0.0002, 0.006)


def ding() -> np.ndarray:
    """Notification PressLink : deux notes de kalimba."""
    s = np.zeros(int(2.4 * SR))
    a, b = kalimba(88, 1.0, 1.8), kalimba(93, 1.0, 2.2)
    s[: len(a)] += a
    i = int(0.13 * SR)
    s[i:i + len(b)] += b[: len(s) - i]
    return s


def signature() -> np.ndarray:
    """Les trois notes montantes du logo."""
    s = np.zeros(int(3.0 * SR))
    for k, m in enumerate((81, 86, 90)):
        x = kalimba(m, 1.0, 2.4)
        i = int(k * 0.16 * SR)
        s[i:i + len(x)] += x[: len(s) - i]
    return s


def bulles(duree: float = 2.0, densite: float = 18) -> np.ndarray:
    n = int(duree * SR)
    s = np.zeros(n)
    for _ in range(int(duree * densite)):
        d = rng.uniform(0.03, 0.08)
        m = int(d * SR)
        ph = glisse(rng.uniform(500, 900), rng.uniform(1200, 2400), d, 2.0)
        b = np.sin(ph) * env(m, 0.002, d / 3)
        i = rng.integers(0, max(1, n - m))
        s[i:i + m] += b * rng.uniform(0.3, 1.0)
    return s


def vapeur(duree: float = 1.0) -> np.ndarray:
    n = int(duree * SR)
    t = np.linspace(0, 1, n)
    e = np.clip(t / 0.05, 0, 1) * np.clip((1 - t) / 0.6, 0, 1)
    return filtre(bruit(n), 2500, 9000) * e


def sonnerie(duree: float = 1.2) -> np.ndarray:
    """Téléphone qui sonne (deux tons modulés, coupé en rafales)."""
    n = int(duree * SR)
    t = np.arange(n) / SR
    s = (np.sin(2 * np.pi * 1300 * t) + np.sin(2 * np.pi * 1650 * t)) * (np.sin(2 * np.pi * 18 * t) > 0)
    rafale = ((t % 0.5) < 0.38).astype(float)
    return filtre(s * rafale, 600, 5000) * 0.5


def klaxon(duree: float = 0.35) -> np.ndarray:
    n = int(duree * SR)
    t = np.arange(n) / SR
    s = sum(np.sign(np.sin(2 * np.pi * f * t)) for f in (392, 494))
    return filtre(s, 300, 3500) * env(n, 0.005, 1.0) * np.clip((duree - t) / 0.03, 0, 1) * 0.4


def boing() -> np.ndarray:
    n = int(0.6 * SR)
    t = np.arange(n) / SR
    f = 140 + 260 * np.exp(-t / 0.25) * (1 + 0.35 * np.sin(2 * np.pi * 14 * t))
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.002, 0.25)


def trombone_triste() -> np.ndarray:
    """« Wah wah wah waaah » : l'échec comique."""
    notes = [(58, 0.32), (57, 0.32), (56, 0.32), (55, 1.1)]
    out = []
    for k, (m, d) in enumerate(notes):
        n = int(d * SR)
        t = np.arange(n) / SR
        vib = 1 + (0.012 * np.sin(2 * np.pi * 5.5 * t) if k == 3 else np.zeros(n))
        ph = 2 * np.pi * np.cumsum(hz(m) * vib) / SR
        s = sum(np.sin(h * ph) / h for h in range(1, 9))
        wah = 400 + 1600 * np.sin(np.pi * np.clip(t / d, 0, 1)) ** 2
        y = np.zeros(n)
        for i in range(0, n, 512):
            y[i:i + 512] = filtre(s[max(0, i - 1024):i + 512], haut=float(wah[i]))[-len(y[i:i + 512]):]
        out.append(y * np.clip(t / 0.03, 0, 1) * np.clip((d - t) / 0.06, 0, 1))
    return np.concatenate(out) * 0.6


def tiroir_caisse() -> np.ndarray:
    """« Ka-ching » : tiroir et clochette."""
    n = int(1.4 * SR)
    t = np.arange(n) / SR
    s = 0.6 * filtre(bruit(n), 1500, 6000) * env(n, 0.001, 0.03)
    i = int(0.09 * SR)
    for f, g in ((2093, 1.0), (2637, 0.7), (3136, 0.5), (4186, 0.3)):
        s[i:] += g * np.sin(2 * np.pi * f * t[: n - i]) * env(n - i, 0.001, 0.35)
    return s * 0.6


def erreur() -> np.ndarray:
    n = int(0.45 * SR)
    t = np.arange(n) / SR
    s = np.sign(np.sin(2 * np.pi * 110 * t)) + 0.5 * np.sign(np.sin(2 * np.pi * 116 * t))
    return filtre(s, 100, 2500) * ((t < 0.18) | (t > 0.24)) * env(n, 0.002, 1.0) * 0.35


def etincelles(duree: float = 1.2) -> np.ndarray:
    n = int(duree * SR)
    s = np.zeros(n)
    for k in range(9):
        m = int(0.5 * SR)
        x = np.sin(2 * np.pi * hz(88 + (k * 5) % 14) * np.arange(m) / SR) * env(m, 0.001, 0.12)
        i = int(k / 9 * (duree - 0.5) * SR)
        s[i:i + m] += x * (0.4 + 0.6 * rng.random())
    return s * 0.5


def imprimante(duree: float = 0.9) -> np.ndarray:
    n = int(duree * SR)
    t = np.arange(n) / SR
    moteur = filtre(np.sign(np.sin(2 * np.pi * 95 * t)), 80, 1200) * 0.25
    cliquetis = filtre(bruit(n), 2500, 7000) * ((t * 38) % 1 < 0.15) * 0.5
    return (moteur + cliquetis) * np.clip(t / 0.03, 0, 1) * np.clip((duree - t) / 0.05, 0, 1)


def papier() -> np.ndarray:
    n = int(0.35 * SR)
    return filtre(bruit(n), 1500, 9000) * env(n, 0.01, 0.08) * (0.6 + 0.4 * np.sin(np.arange(n) / SR * 2 * np.pi * 30))


def tic() -> np.ndarray:
    n = int(0.04 * SR)
    return filtre(bruit(n), 3000, 9000) * env(n, 0.0002, 0.005) * 0.8


# ---------------------------------------------------------------- mixage
def duck(mix: np.ndarray, plages: list[tuple[float, float]], niveau: float = 0.38) -> np.ndarray:
    gain = np.ones(len(mix))
    t = np.arange(len(mix)) / SR
    for a, b in plages:
        g = 1 - (1 - niveau) * np.minimum(np.clip((t - (a - 0.15)) / 0.15, 0, 1), np.clip(((b + 0.3) - t) / 0.3, 0, 1))
        gain = np.minimum(gain, g)
    return mix * gain[:, None]


def mixer(timeline: dict, dossier_voix: str, musique: np.ndarray, sfx: np.ndarray, sortie: str) -> None:
    """Voix traitées + musique duckée + bruitages → wav normalisé -14 LUFS."""
    duree = timeline['duree']
    durees = {r['cle']: r['duree'] for r in json.load(open(f'{dossier_voix}/repliques.json'))}
    plages = [(v['debut'], v['debut'] + durees[v['cle']]) for v in timeline['voix']]
    fond = duck(musique, plages) + sfx
    n = int(duree * SR)
    fond = fond[:n]
    f = int(1.0 * SR)
    fond[-f:] *= np.linspace(1, 0, f)[:, None] ** 2
    fond /= max(1e-9, np.max(np.abs(fond))) / 0.6
    wavfile.write(f'{dossier_voix}/fond.wav', SR, fond.astype(np.float32))

    entrees, filtres, labels = ['-i', f'{dossier_voix}/fond.wav'], [], []
    for i, v in enumerate(timeline['voix'], start=1):
        entrees += ['-i', f"{dossier_voix}/{v['cle']}.wav"]
        d = int(v['debut'] * 1000)
        filtres.append(
            f'[{i}:a]aresample=48000,highpass=f=70,equalizer=f=190:t=q:w=0.9:g=2,'
            'equalizer=f=3200:t=q:w=1.4:g=1.5,equalizer=f=7000:t=q:w=1.5:g=-2,'
            'acompressor=threshold=-21dB:ratio=2.6:attack=8:release=140:makeup=2.2,'
            f'adelay={d}|{d},pan=stereo|c0=c0|c1=c0[v{i}]')
        labels.append(f'[v{i}]')
    filtres.append(f"{''.join(labels)}amix=inputs={len(labels)}:normalize=0[voix]")
    filtres.append(f'[voix][0:a]amix=inputs=2:normalize=0:duration=longest,atrim=0:{duree},loudnorm=I=-14:TP=-1.5:LRA=9[out]')
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', *entrees, '-filter_complex', ';'.join(filtres),
                    '-map', '[out]', '-ar', '48000', sortie], check=True)
    print('mix ->', sortie)
