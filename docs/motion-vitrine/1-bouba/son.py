"""Bande son de « Le voyage de Bouba » : afro-pop sautillant (marimba,
pizzicato, claps) et bruitages cartoon calés sur l'image.

Usage : python son.py <dossier_voix> <sortie.wav>
"""
import json
import sys
from pathlib import Path

ICI = Path(__file__).parent
sys.path.insert(0, str(ICI.parent / 'commun'))
from audio import *  # noqa: E402,F403

BPM = 104
TEMPS = 60 / BPM
DC = TEMPS / 4
MESURE = TEMPS * 4
DEPART = 0.9  # le groove démarre avec « Aïe aïe aïe »

# Fa majeur : Fa – Ré m – Si♭ – Do
GRILLE = [(41, [53, 57, 60]), (38, [50, 53, 57]), (34, [46, 50, 53]), (36, [48, 52, 55])]
MELODIE = [  # (double croche, note) sur deux mesures, pentatonique joyeuse
    (0, 77), (3, 81), (6, 84), (8, 81), (10, 79), (12, 77), (14, 74),
    (16, 77), (19, 79), (22, 81), (24, 84), (26, 86), (28, 84), (30, 81),
]


def musique(duree: float, fin_groove: float) -> np.ndarray:
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    # intro : petite boîte à musique endormie
    for k, n in enumerate((77, 81, 84, 81)):
        poser(m, kalimba(n, 0.5, 1.6), 0.1 + k * 0.2, 0.3, pan=-0.2)
    t0, i = DEPART, 0
    while t0 < fin_groove:
        racine, accord = GRILLE[i % 4]
        lavage = 10.5 <= t0 < 14.4
        for pos in range(16):
            t = t0 + pos * DC
            if t >= fin_groove:
                break
            if pos % 4 == 0:
                poser(perc, grosse_caisse(0.9), t, 0.5)
            if pos in (4, 12):
                poser(perc, clap(0.9), t, 0.2, pan=0.15)
            poser(perc, shaker(1.0 if pos % 2 else 0.5), t, 0.08, pan=0.5)
            if pos in (3, 7, 11, 14):
                poser(perc, tambour(260 if pos != 7 else 190, 0.8), t, 0.18, pan=-0.35)
        # basse sautillante (octaves) en pizzicato
        for pos, octave in ((0, 0), (3, 12), (6, 0), (8, 7), (10, 12), (12, 0), (14, 7)):
            poser(bas, pluck(racine + 12 + octave, 0.35, 0.9, 0.3), t0 + pos * DC, 0.55)
            poser(bas, basse(racine + (octave if octave != 7 else 7), DC * 1.6, 0.7), t0 + pos * DC, 0.25)
        # accords marimba en contretemps
        for pos in (2, 6, 10, 14):
            for k, n in enumerate(accord):
                poser(m, marimba(n + 12, 0.7), t0 + pos * DC + k * 0.008, 0.16, pan=-0.25 + 0.25 * k)
        # mélodie (pas pendant le lavage : on laisse place aux bulles)
        if not lavage:
            for pos, n in MELODIE:
                if (i % 2) * 16 <= pos < (i % 2 + 1) * 16:
                    poser(m, marimba(n, 0.9), t0 + (pos % 16) * DC, 0.24, pan=0.2)
        else:
            poser(m, nappe([n + 12 for n in accord], MESURE, 0.6, 2200), t0, 0.5)
        t0 += MESURE
        i += 1
    # accord final sur le logo
    poser(m, nappe([53, 57, 60, 65], 2.4, 0.5), fin_groove, 0.6)
    for k, n in enumerate((77, 81, 84, 89)):
        poser(m, kalimba(n, 0.9, 3.0), fin_groove + k * 0.07, 0.3)
    poser(bas, basse(41, 1.4), fin_groove, 0.5)
    return reverb(m, 1.6, 0.22) + reverb(perc, 0.8, 0.06) + bas


def bruitages(duree: float) -> np.ndarray:
    s = piste(duree)
    for t in (0.25, 2.1, 7.1, 8.3, 12.5, 15.6, 19.3, 21.1, 21.7):
        poser(s, pop(1.0 if t < 20 else 1.3), t, 0.35)
    poser(s, boing(), 0.95, 0.45)
    for t, d, haut in ((4.3, 0.6, True), (4.8, 0.5, False), (6.75, 0.6, True), (10.45, 0.5, False), (14.35, 0.5, False), (16.15, 0.5, False), (20.15, 0.6, False)):
        poser(s, souffle(d, haut), t - 0.2, 0.22)
    poser(s, klaxon(0.22), 5.45, 0.5, pan=-0.2)
    poser(s, klaxon(0.32), 5.75, 0.5, pan=-0.2)
    poser(s, clic(), 8.1, 0.6)
    poser(s, imprimante(0.85), 8.7, 0.35, pan=0.5)
    poser(s, papier(), 9.7, 0.4, pan=0.2)
    poser(s, bulles(3.8, 22), 10.6, 0.18)
    poser(s, vapeur(1.3), 14.7, 0.22)
    poser(s, etincelles(1.2), 15.5, 0.35)
    poser(s, ding(), 16.5, 0.55)
    poser(s, etincelles(1.4), 16.8, 0.25)
    poser(s, signature(), 20.45, 0.5)
    poser(s, boing(), 22.6, 0.18)
    return reverb(s, 1.0, 0.12)


def main(dossier_voix: str, sortie: str) -> None:
    timeline = json.load(open(ICI / 'timeline.json'))
    duree = timeline['duree']
    mixer(timeline, dossier_voix, musique(duree, 20.2), bruitages(duree), sortie)


if __name__ == '__main__':
    main(*sys.argv[1:3])
