"""Musique, bruitages et mixage de la voix off du parcours pressing.

Usage : python3 son.py <plan.json> <dossier_voix> <sortie.wav>
plan.json vient de « node fabriquer.cjs plan » : durée, début du film, carte de fin, voix et repères.
Musique : un groove calme et lumineux, plus dense à chaque chapitre, qui s'efface sous la voix.
"""
import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'motion-vitrine', 'commun'))
from audio import *  # noqa: E402,F403


def groove(m, perc, bas, debut: float, fin: float, chapitres: list) -> None:
    temps = 60 / 96
    dc = temps / 4
    grilles = [
        [(38, [74, 81, 78, 86]), (35, [71, 78, 74, 83]), (31, [79, 83, 86, 91]), (33, [76, 81, 85, 88])],
        [(31, [74, 79, 83, 86]), (33, [76, 81, 85, 88]), (35, [74, 78, 83, 86]), (38, [78, 81, 86, 90])],
    ]
    i = 0
    while debut + i * temps * 4 < fin - 0.1:
        t0 = debut + i * temps * 4
        n_chap = sum(1 for c in chapitres if c <= t0)
        grille = grilles[n_chap % 2]
        racine, arp = grille[i % 4]
        fort = n_chap >= 1 and (i % 16) not in (0, 15)
        for pos in range(16):
            x = t0 + pos * dc
            if x >= fin:
                break
            poser(perc, shaker(1.0 if pos % 4 == 2 else 0.5), x, 0.06, pan=0.45)
            if fort and pos % 8 == 0:
                poser(perc, grosse_caisse(0.7), x, 0.26)
            if fort and pos == 12:
                poser(perc, clap(0.6), x, 0.07)
            if pos in (0, 3, 6, 10, 12):
                poser(m, kalimba(arp[pos % 4], 0.7), x, 0.16, pan=-0.3 + 0.08 * (pos % 8))
        for pos, d in ((0, 3), (6, 2), (10, 2)):
            if t0 + pos * dc < fin:
                poser(bas, basse(racine, d * dc * 0.9), t0 + pos * dc, 0.26)
        poser(m, nappe([racine + 24, racine + 28, racine + 31], min(temps * 4, fin - t0), 0.45), t0, 0.26)
        i += 1


def main(plan_json: str, dossier_voix: str, sortie: str) -> None:
    plan = json.load(open(plan_json))
    duree, intro, t_fin = plan['duree'], plan['intro'], plan['fin']
    chapitres = [r['t'] for r in plan['reperes'] if r['son'] == 'souffle']
    m, perc, bas, sfx = piste(duree), piste(duree), piste(duree), piste(duree)

    # ouverture : nappe de ré majeur et petite cascade
    poser(m, nappe([62, 66, 69, 74], intro + 1.0, 0.6), 0.0, 0.35)
    for k, n in enumerate((74, 78, 81, 86)):
        poser(m, kalimba(n, 0.7, 2.0), 0.5 + k * 0.25, 0.16, pan=-0.3 + 0.2 * k)
    groove(m, perc, bas, intro - 0.2, t_fin + 0.3, chapitres)

    # carte de fin
    poser(m, nappe([50, 62, 66, 69, 74], duree - t_fin - 0.8, 0.7), t_fin, 0.4)
    for k, n in enumerate((74, 78, 81, 86, 90)):
        poser(m, kalimba(n, 0.8, 2.5), t_fin + 0.9 + k * 0.22, 0.2, pan=-0.4 + 0.2 * k)
    poser(bas, basse(38, 3.0, 0.8), t_fin, 0.35)

    sons = {
        'souffle': (souffle(0.6), 0.14), 'clic': (clic(), 0.10), 'ding': (ding(), 0.34), 'montee': (souffle(0.9, True), 0.22),
    }
    for r in plan['reperes']:
        son, gain = sons[r['son']]
        poser(sfx, son, r['t'], gain)

    musique = reverb(m, 1.4, 0.18) + reverb(perc, 0.6, 0.05) + bas
    mixer(plan, dossier_voix, musique, reverb(sfx, 0.5, 0.06) * 0.8, sortie)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3])
