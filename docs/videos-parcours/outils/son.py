"""Bande son d'une vidéo de parcours : kalimba afro-pop douce sous la voix,
clic à chaque toucher, petit « pop » à chaque puce, signature au début et à la fin.

Usage : python son.py <plan.json> <dossier_voix> <sortie.wav>
"""
import json
import sys

from audio import *  # noqa: F403


def musique(duree: float) -> np.ndarray:
    temps = 60 / 100
    dc = temps / 4
    grille = [(38, [74, 81, 78, 86]), (35, [71, 78, 74, 83]), (31, [79, 83, 86, 91]), (33, [76, 81, 85, 88])]
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    t0, i = 0.0, 0
    while t0 < duree - 1:
        racine, arp = grille[i % 4]
        for pos in range(16):
            x = t0 + pos * dc
            poser(perc, shaker(1.0 if pos % 4 == 2 else 0.5), x, 0.05, pan=0.45)
            if pos % 8 == 0 and i > 0:
                poser(perc, grosse_caisse(0.7), x, 0.3)
            if pos in (0, 6, 10):
                poser(m, kalimba(arp[pos % 4], 0.7), x, 0.18, pan=-0.3 + 0.08 * (pos % 8))
        for pos, d in ((0, 3), (6, 2), (10, 2)):
            poser(bas, basse(racine, d * dc * 0.9), t0 + pos * dc, 0.25)
        poser(m, nappe([racine + 24, racine + 28, racine + 31], temps * 4, 0.5), t0, 0.3)
        t0 += temps * 4
        i += 1
    return reverb(m, 1.4, 0.2) + reverb(perc, 0.6, 0.05) + bas


def main(chemin_plan: str, dossier_voix: str, sortie: str) -> None:
    plan = json.load(open(chemin_plan))
    duree = plan['duree']
    sfx = piste(duree)
    for t in plan['touchers']:
        poser(sfx, clic(), t, 0.35)
    for t in plan['puces']:
        poser(sfx, pop(1.15), t, 0.18)
    for t in plan['signature']:
        poser(sfx, signature(), t, 0.4)
    if plan.get('notification'):
        poser(sfx, ding(), plan['notification'], 0.55)
    mixer({'duree': duree, 'voix': [{'cle': v['cle'], 'debut': v['debut']} for v in plan['voix']]}, dossier_voix, musique(duree), reverb(sfx, 0.7, 0.08), sortie)


if __name__ == '__main__':
    main(*sys.argv[1:4])
