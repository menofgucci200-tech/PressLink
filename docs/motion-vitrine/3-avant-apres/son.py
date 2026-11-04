"""Bande son de « Avant / Après » : musique comique en mineur pendant le
« avant » (pizzicato, tuba, tic-tac), groove kalimba lumineux quand PressLink
prend le relais, et bruitages de sketch (sonnerie, boing, trombone…).

Usage : python son.py <dossier_voix> <sortie.wav>
"""
import json
import sys
from pathlib import Path

ICI = Path(__file__).parent
sys.path.insert(0, str(ICI.parent / 'commun'))
from audio import *  # noqa: E402,F403

AVANT = [(1.0, 6.8), (10.4, 14.2)]
APRES = [(6.8, 10.4), (14.2, 18.6)]
FIN = 18.6


def galere(m: np.ndarray, perc: np.ndarray, bas: np.ndarray, a: float, b: float) -> None:
    """Ré mineur sautillant à 132 BPM : tuba + pizzicato + tic-tac."""
    temps = 60 / 132
    basses = [38, 45, 38, 45, 41, 45, 40, 37]
    melodie = [62, 65, 69, 65, 64, 61, 62, None, 62, 65, 69, 72, 70, 69, 67, 64]
    k, t = 0, a
    while t < b - 0.05:
        poser(bas, basse(basses[k % 8], temps * 0.45, 0.9), t, 0.5)
        poser(perc, tic(), t, 0.25, pan=0.5)
        poser(perc, tic(), t + temps / 2, 0.12, pan=0.5)
        for j in (0, 1):
            n = melodie[(2 * k + j) % 16]
            if n:
                poser(m, pluck(n + 12, 0.28, 0.8, 0.4), t + j * temps / 2, 0.2, pan=-0.3)
        if k % 2 == 1:
            poser(m, marimba(74 if k % 4 == 1 else 73, 0.6), t, 0.12, pan=0.3)
        t += temps
        k += 1


def soleil(m: np.ndarray, perc: np.ndarray, bas: np.ndarray, a: float, b: float, final: bool = False) -> None:
    """Ré majeur, groove kalimba afro-pop à 100 BPM."""
    temps = 60 / 100
    dc = temps / 4
    grille = [(38, [74, 81, 78, 86]), (35, [71, 78, 74, 83]), (31, [79, 83, 86, 91]), (33, [76, 81, 85, 88])]
    t, i = a, 0
    while t < b - 0.05:
        racine, arp = grille[i % 4]
        for pos in range(16):
            x = t + pos * dc
            if x >= b:
                break
            poser(perc, shaker(1.0 if pos % 4 == 2 else 0.5), x, 0.09, pan=0.45)
            if pos % 8 == 0:
                poser(perc, grosse_caisse(0.8), x, 0.45)
            if pos in (4, 12):
                poser(perc, clap(0.7), x, 0.14)
            if pos in (0, 3, 6, 8, 10, 12, 14):
                poser(m, kalimba(arp[pos % 4], 0.8), x, 0.26, pan=-0.3 + 0.08 * (pos % 8))
        for pos, d in ((0, 3), (6, 2), (10, 2), (13, 2)):
            poser(bas, basse(racine, d * dc * 0.9), t + pos * dc, 0.4)
        poser(m, nappe([racine + 24, racine + 28, racine + 31], temps * 4, 0.5), t, 0.35)
        t += temps * 4
        i += 1
    if final:
        poser(m, nappe([50, 54, 57, 62], 2.2, 0.5), b, 0.5)
        for k, n in enumerate((74, 78, 81, 86)):
            poser(m, kalimba(n, 0.9, 3.0), b + k * 0.07, 0.3)
        poser(bas, basse(38, 1.4), b, 0.45)


def musique(duree: float) -> np.ndarray:
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    for a, b in AVANT:
        galere(m, perc, bas, a, b)
    for a, b in APRES:
        soleil(m, perc, bas, a, b)
    soleil(m, perc, bas, FIN, 22.6, final=True)
    return reverb(m, 1.3, 0.18) + reverb(perc, 0.6, 0.05) + bas


def bruitages(duree: float) -> np.ndarray:
    s = piste(duree)
    poser(s, souffle(0.6), 0.0, 0.3, pan=-0.4)
    poser(s, souffle(0.6), 0.1, 0.3, pan=0.4)
    poser(s, impact(), 0.7, 0.3)
    for t in (1.0, 2.0, 3.1, 4.9, 10.4, 11.0, 12.2, 12.3, 12.4, 20.0, 21.2):
        poser(s, pop(), t, 0.3)
    for i in range(6):
        poser(s, pop(1.1 + 0.08 * i), 5.3 + i * 0.2, 0.28, pan=-0.5 + 0.2 * i)
    poser(s, sonnerie(0.55), 1.3, 0.35)
    poser(s, clic(), 1.85, 0.6)
    for i in range(7):
        poser(s, papier(), 3.4 + i * 0.4, 0.25, pan=-0.6 + 0.2 * i)
    poser(s, boing(), 5.8, 0.4)
    trombone = trombone_triste()[: int(1.3 * SR)]
    trombone[-int(0.25 * SR):] *= np.linspace(1, 0, int(0.25 * SR))
    poser(s, trombone, 5.95, 0.4)
    for t in (6.8, 10.4, 14.2):
        poser(s, souffle(0.5), t - 0.15, 0.25)
    poser(s, ding(), 7.1, 0.55)
    poser(s, pop(1.3), 7.7, 0.3)
    for i in range(5):
        poser(s, papier(), 10.8 + i * 0.22, 0.2)
    poser(s, erreur(), 13.9, 0.45)
    for t in (14.5, 14.9, 15.1, 15.5, 15.9):
        poser(s, clic(), t, 0.4)
    for k in range(12):
        poser(s, tic(), 16.0 + k * 0.07, 0.3)
    poser(s, tiroir_caisse(), 17.0, 0.55)
    poser(s, souffle(0.7, True), 18.3, 0.3)
    poser(s, impact(), 18.9, 0.3)
    poser(s, signature(), 18.95, 0.5)
    return reverb(s, 0.9, 0.1)


def main(dossier_voix: str, sortie: str) -> None:
    timeline = json.load(open(ICI / 'timeline.json'))
    mixer(timeline, dossier_voix, musique(timeline['duree']), bruitages(timeline['duree']), sortie)


if __name__ == '__main__':
    main(*sys.argv[1:3])
