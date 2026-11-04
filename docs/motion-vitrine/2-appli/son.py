"""Bande son de « L'appli prend vie » : afro-house 120 BPM (grosse caisse
régulière, log drum, guitare pincée, accords courts) et sons d'interface
calés sur les temps.

Usage : python son.py <dossier_voix> <sortie.wav>
"""
import json
import sys
from pathlib import Path

ICI = Path(__file__).parent
sys.path.insert(0, str(ICI.parent / 'commun'))
from audio import *  # noqa: E402,F403

TEMPS = 0.5
DC = TEMPS / 4
MESURE = TEMPS * 4
ORIGINE = 0.5
# La mineur : Am7 – Fmaj7 – C – G
GRILLE = [(45, [57, 60, 64, 67]), (41, [57, 60, 64, 65]), (48, [55, 60, 64, 67]), (43, [55, 59, 62, 67])]
LOG = [(0, 0), (3, 0), (6, 7), (10, 12), (11, 10), (14, 7)]
RIFF = [(0, 76), (2, 79), (3, 81), (6, 79), (8, 76), (10, 74), (11, 76), (14, 72)]


def musique(duree: float) -> np.ndarray:
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    t0, i = ORIGINE, 0
    while t0 < duree - 0.5:
        racine, accord = GRILLE[i % 4]
        pause = 17.0 <= t0 < 18.0          # respiration avant le logo
        final = t0 >= 21.0
        for pos in range(16):
            t = t0 + pos * DC
            if pause or final:
                continue
            if pos % 4 == 0:
                poser(perc, grosse_caisse(1.0, profond=True), t, 0.62)
            if pos % 4 == 2:
                poser(perc, charleston(True, 0.9), t, 0.12, pan=0.25)
            poser(perc, shaker(0.9 if pos % 2 else 0.45), t + (0.012 if pos % 2 else 0), 0.07, pan=-0.45)
            if pos in (4, 12):
                poser(perc, clap(1.0), t, 0.2)
            if pos in (7, 15):
                poser(perc, tambour(300, 0.6), t, 0.16, pan=0.4)
        if not pause and not final:
            for pos, d in LOG:
                poser(bas, log_drum(racine - 12 + d + 12, 0.95), t0 + pos * DC, 0.5)
            for pos in (2, 6, 10, 13):
                poser(m, stab([n + 12 for n in accord], 0.8), t0 + pos * DC, 0.1, pan=-0.2 if pos % 4 == 2 else 0.2)
            if t0 >= 4.5:
                for pos, n in RIFF:
                    poser(m, pluck(n - 12, 0.35, 0.8, 0.7), t0 + pos * DC, 0.16, pan=0.35)
        poser(m, nappe(accord, MESURE, 0.5, 1800), t0, 0.35 if not final else 0.55)
        t0 += MESURE
        i += 1
    for k, n in enumerate((69, 72, 76, 81)):
        poser(m, kalimba(n, 0.8, 3.0), 21.0 + k * 0.06, 0.25)
    poser(bas, basse(33, 1.6), 21.0, 0.5)
    return reverb(m, 1.4, 0.2) + reverb(perc, 0.6, 0.05) + bas


def bruitages(duree: float) -> np.ndarray:
    s = piste(duree)
    poser(s, souffle(0.55, True), 0.0, 0.35)
    poser(s, souffle(1.0, True), 17.0, 0.35)
    for t in (0.5, 9.5, 18.0):
        poser(s, impact(), t, 0.5)
    for t in (4.4, 6.9, 13.9):
        poser(s, souffle(0.5), t - 0.15, 0.25)
    for t in (1.45, 2.45, 2.95):
        poser(s, clic(), t, 0.6)
    poser(s, ding(), 7.5, 0.6)
    for t in (5.5, 6.0, 6.5, 14.5, 15.0, 15.5, 16.5, 17.0, 17.5, 20.5):
        poser(s, pop(1.2), t, 0.3)
    for t in (10.0, 10.5, 11.0, 11.5):
        poser(s, clic(), t, 0.4)
    for k in range(14):
        poser(s, tic(), 11.6 + k * 0.07, 0.35)
    poser(s, tiroir_caisse(), 12.75, 0.55)
    poser(s, signature(), 18.25, 0.5)
    return reverb(s, 0.9, 0.1)


def main(dossier_voix: str, sortie: str) -> None:
    timeline = json.load(open(ICI / 'timeline.json'))
    mixer(timeline, dossier_voix, musique(timeline['duree']), bruitages(timeline['duree']), sortie)


if __name__ == '__main__':
    main(*sys.argv[1:3])
