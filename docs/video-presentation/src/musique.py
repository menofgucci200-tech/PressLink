"""Musique et effets sonores des vidéos PressLink, synthétisés note à note.

Identité sonore : kalimba (likembé) en ré majeur pentatonique sur un groove
afro-pop doux (96 BPM), avec une signature de trois notes montantes pour le
logo et un carillon de notification.

Usage : python musique.py <timeline.json> <repliques.json> <sortie.wav>
"""
import json
import sys

import numpy as np
from scipy.signal import butter, fftconvolve, sosfilt

SR = 48_000
BPM = 96
BEAT = 60 / BPM
DOUBLE = BEAT / 4          # double croche
MESURE = BEAT * 4
ORIGINE = -0.4             # la grille tombe sur l'arrivée du groove à 4,6 s
GROOVE = 4.6

rng = np.random.default_rng(7)


def hz(midi: float) -> float:
    return 440.0 * 2 ** ((midi - 69) / 12)


def piste(duree: float) -> np.ndarray:
    return np.zeros((int(duree * SR) + SR, 2), dtype=np.float64)


def poser(dest: np.ndarray, son: np.ndarray, t: float, gain: float = 1.0, pan: float = 0.0) -> None:
    """Ajoute un son mono (ou stéréo) à l'instant t, avec panoramique."""
    if son.ndim == 1:
        g = np.array([np.cos((pan + 1) * np.pi / 4), np.sin((pan + 1) * np.pi / 4)]) * np.sqrt(2)
        son = son[:, None] * g[None, :]
    i = int(t * SR)
    if i >= len(dest):
        return
    j = min(len(dest), i + len(son))
    dest[i:j] += son[: j - i] * gain


def enveloppe(n: int, attaque: float, decroissance: float) -> np.ndarray:
    t = np.arange(n) / SR
    a = np.clip(t / max(attaque, 1e-4), 0, 1)
    return a * np.exp(-t / decroissance)


# ---------------------------------------------------------------- instruments
def kalimba(midi: float, vel: float = 1.0, duree: float = 2.2) -> np.ndarray:
    n = int(duree * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    corps = np.sin(2 * np.pi * f * t) * enveloppe(n, 0.002, 0.75)
    corps += 0.35 * np.sin(2 * np.pi * f * 2.0 * t + 0.3) * enveloppe(n, 0.002, 0.28)
    tine = 0.22 * np.sin(2 * np.pi * f * 5.93 * t) * enveloppe(n, 0.0005, 0.045)
    clic = rng.normal(0, 1, n) * enveloppe(n, 0.0002, 0.004) * 0.08
    son = (corps + tine + clic) * vel
    # légère « boîte » de résonance
    son += 0.15 * np.sin(2 * np.pi * (f * 0.5) * t) * enveloppe(n, 0.004, 0.18) * vel
    return son


def basse(midi: float, duree: float, vel: float = 1.0) -> np.ndarray:
    n = int((duree + 0.25) * SR)
    t = np.arange(n) / SR
    f = hz(midi)
    env = np.clip(t / 0.008, 0, 1) * np.where(t < duree, 1.0, np.exp(-(t - duree) / 0.06)) * np.exp(-t / 1.4)
    son = np.sin(2 * np.pi * f * t) + 0.28 * np.sin(2 * np.pi * 2 * f * t) + 0.08 * np.sin(2 * np.pi * 3 * f * t)
    return np.tanh(1.4 * son) / 1.4 * env * vel


def grosse_caisse(vel: float = 1.0) -> np.ndarray:
    n = int(0.45 * SR)
    t = np.arange(n) / SR
    f = 48 + 70 * np.exp(-t / 0.035)
    phase = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(phase) * enveloppe(n, 0.001, 0.16) * vel


def shaker(vel: float = 1.0) -> np.ndarray:
    n = int(0.09 * SR)
    bruit = rng.normal(0, 1, n)
    sos = butter(4, [5000, 11000], btype='bandpass', fs=SR, output='sos')
    return sosfilt(sos, bruit) * enveloppe(n, 0.006, 0.025) * vel


def tambour(f: float, vel: float = 1.0) -> np.ndarray:
    """Percussion à peau (djembé/conga très adouci)."""
    n = int(0.35 * SR)
    t = np.arange(n) / SR
    ton = np.sin(2 * np.pi * f * (1 + 0.25 * np.exp(-t / 0.02)) * t) * enveloppe(n, 0.001, 0.09)
    sos = butter(2, [f * 1.5, f * 6], btype='bandpass', fs=SR, output='sos')
    peau = sosfilt(sos, rng.normal(0, 1, n)) * enveloppe(n, 0.0005, 0.02) * 0.6
    return (ton + peau) * vel


def claquement(vel: float = 1.0) -> np.ndarray:
    n = int(0.12 * SR)
    sos = butter(2, [1200, 4500], btype='bandpass', fs=SR, output='sos')
    son = sosfilt(sos, rng.normal(0, 1, n)) * enveloppe(n, 0.0008, 0.03)
    return son * vel


def nappe(notes: list[int], duree: float, vel: float = 1.0) -> np.ndarray:
    n = int((duree + 0.8) * SR)
    t = np.arange(n) / SR
    son = np.zeros(n)
    for m in notes:
        for desaccord in (-0.06, 0.06):
            f = hz(m + desaccord)
            for h in range(1, 7):
                son += np.sin(2 * np.pi * f * h * t + h) / h ** 1.3
    sos = butter(2, 1400, btype='lowpass', fs=SR, output='sos')
    son = sosfilt(sos, son)
    env = np.clip(t / 0.5, 0, 1) * np.where(t < duree, 1.0, np.exp(-(t - duree) / 0.35))
    return son / (len(notes) * 4) * env * vel


def souffle(duree: float = 0.55) -> np.ndarray:
    """Transition : souffle filtré qui monte puis s'évanouit."""
    n = int(duree * SR)
    bruit = rng.normal(0, 1, n)
    out = np.zeros(n)
    bloc = 256
    for i in range(0, n, bloc):
        x = i / n
        centre = 300 + 3200 * np.sin(np.pi * x) ** 1.5
        sos = butter(2, [centre * 0.6, min(centre * 1.6, 20000)], btype='bandpass', fs=SR, output='sos')
        out[i:i + bloc] = sosfilt(sos, bruit[max(0, i - 512):i + bloc])[-len(out[i:i + bloc]):]
    env = np.sin(np.pi * np.linspace(0, 1, n)) ** 2
    return out * env


def pop() -> np.ndarray:
    n = int(0.08 * SR)
    t = np.arange(n) / SR
    f = 520 + 600 * np.exp(-t / 0.012)
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * enveloppe(n, 0.0005, 0.02)


def reverb(stereo: np.ndarray, temps: float = 1.6, mouille: float = 0.2) -> np.ndarray:
    n = int(temps * SR)
    t = np.arange(n) / SR
    sos = butter(1, 5000, btype='lowpass', fs=SR, output='sos')
    ir = [sosfilt(sos, rng.normal(0, 1, n)) * np.exp(-t / (temps / 4.5)) for _ in range(2)]
    for x in ir:
        x[: int(0.012 * SR)] = 0
        x /= np.sqrt(np.sum(x ** 2))
    wet = np.stack([fftconvolve(stereo[:, c], ir[c])[: len(stereo)] for c in range(2)], axis=1)
    return stereo + wet * mouille


# ---------------------------------------------------------------- composition
ACCORDS = [  # (fondamentale basse, notes de nappe, arpège kalimba)
    (38, [50, 54, 57], [74, 81, 78, 86, 81, 78, 81]),   # Ré
    (35, [47, 50, 54], [71, 78, 74, 83, 78, 74, 78]),   # Si mineur
    (31, [43, 47, 50], [79, 83, 86, 91, 86, 83, 86]),   # Sol
    (33, [45, 49, 52], [76, 81, 85, 88, 85, 81, 85]),   # La
]
ARPEGE_POS = [0, 3, 6, 8, 10, 12, 14]
SIGNATURE = [81, 86, 90]


def composer(duree: float, sons: dict) -> np.ndarray:
    melo, perc, bas = piste(duree), piste(duree), piste(duree)
    fin_groove = duree - 2.4
    mesure = 0
    t_mesure = ORIGINE
    while t_mesure < duree:
        racine, nap, arp = ACCORDS[mesure % 4]
        groove = t_mesure >= GROOVE - 0.01 and t_mesure < fin_groove
        intro = t_mesure < GROOVE - 0.01
        # nappe (toujours, plus présente dans l'intro)
        poser(melo, nappe(nap, MESURE * 0.98, 0.55 if intro else 0.35), max(t_mesure, 0))
        for k, pos in enumerate(ARPEGE_POS):
            t = t_mesure + pos * DOUBLE
            if t < 0 or t >= fin_groove + MESURE:
                continue
            if intro and pos not in (0, 6, 10):
                continue
            vel = (0.95 if pos in (0, 8) else 0.7) * (0.75 if intro else 1.0)
            poser(melo, kalimba(arp[k], vel), t, 0.32, pan=-0.35 + 0.1 * k)
        if groove:
            for pos in range(16):
                t = t_mesure + pos * DOUBLE
                poser(perc, shaker(1.0 if pos % 4 == 2 else 0.55), t, 0.11, pan=0.45)
            for pos, v in ((0, 1.0), (8, 0.7)):
                poser(perc, grosse_caisse(v), t_mesure + pos * DOUBLE, 0.55)
            for pos, f, v in ((3, 330, 0.7), (7, 210, 0.9), (11, 330, 0.6), (14, 250, 0.8)):
                poser(perc, tambour(f, v), t_mesure + pos * DOUBLE, 0.22, pan=-0.3)
            for pos in (4, 12):
                poser(perc, claquement(0.8), t_mesure + pos * DOUBLE, 0.16, pan=0.2)
            for pos, longueur, octave in ((0, 3, 0), (6, 2, 0), (10, 2, 12), (13, 2, 0)):
                poser(bas, basse(racine + octave, longueur * DOUBLE * 0.9), t_mesure + pos * DOUBLE, 0.42)
        elif intro:
            for pos in (0, 4, 8, 12):  # tic-tac discret de l'attente
                t = t_mesure + pos * DOUBLE
                if t >= 0:
                    poser(perc, shaker(0.5), t, 0.08, pan=0.4)
        t_mesure += MESURE
        mesure += 1

    # accord final qui résonne
    poser(melo, nappe([50, 54, 57, 62], 2.0, 0.4), fin_groove)
    for i, m in enumerate([74, 78, 81, 86]):
        poser(melo, kalimba(m, 0.8, 3.0), fin_groove + i * 0.07, 0.26)
    poser(bas, basse(38, 1.2), fin_groove, 0.42)

    # signature sonore et effets
    for t0 in sons['signature_sonore']:
        for i, m in enumerate(SIGNATURE):
            poser(melo, kalimba(m, 1.0, 2.6), t0 + i * 0.16, 0.42, pan=-0.2 + 0.2 * i)
    for t0 in sons['notification']:
        poser(melo, kalimba(88, 1.0, 1.8), t0, 0.45, pan=0.15)
        poser(melo, kalimba(93, 1.0, 2.2), t0 + 0.13, 0.45, pan=0.25)
    sfx = piste(duree)
    for t0 in sons['souffles']:
        poser(sfx, souffle(), t0 - 0.3, 0.05)
    for t0 in sons['pops']:
        poser(sfx, pop(), t0, 0.18)

    mix = reverb(melo, 1.8, 0.28) + reverb(perc, 0.9, 0.08) + bas + reverb(sfx, 1.2, 0.15)
    return mix[: int(duree * SR)]


def duck(mix: np.ndarray, plages: list[tuple[float, float]], niveau: float = 0.42) -> np.ndarray:
    gain = np.ones(len(mix))
    t = np.arange(len(mix)) / SR
    for a, b in plages:
        montee = np.clip((t - (a - 0.15)) / 0.15, 0, 1)
        descente = np.clip(((b + 0.35) - t) / 0.35, 0, 1)
        g = 1 - (1 - niveau) * np.minimum(montee, descente)
        gain = np.minimum(gain, g)
    return mix * gain[:, None]


def main(timeline_path: str, repliques_path: str, sortie: str) -> None:
    from scipy.io import wavfile

    timeline = json.load(open(timeline_path))
    durees = {r['cle']: r['duree'] for r in json.load(open(repliques_path))}
    duree = timeline['duree']
    mix = composer(duree, timeline['sons'])
    plages = [(v['debut'], v['debut'] + durees[v['cle']]) for v in timeline['voix']]
    mix = duck(mix, plages)
    # fondu de fin
    n = int(1.2 * SR)
    mix[-n:] *= np.linspace(1, 0, n)[:, None] ** 2
    mix /= max(1e-9, np.max(np.abs(mix))) / 0.7
    wavfile.write(sortie, SR, mix.astype(np.float32))
    print('musique', round(len(mix) / SR, 2), 's ->', sortie)


if __name__ == '__main__':
    main(*sys.argv[1:4])
