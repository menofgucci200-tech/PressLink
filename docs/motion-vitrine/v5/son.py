"""Musique, bruitages et mixage de la voix off du film PressLink V2.

Usage : python3 son.py <plan.json> <dossier_voix> <sortie.wav>
plan.json vient de « node fabriquer.cjs plan » : durée, sections, repères sonores et voix.
Musique : tension discrète pendant le problème, puis groove lumineux dès « PressLink simplifie tout ».
"""
import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'commun'))
from audio import *  # noqa: E402,F403


def tension(m, perc, bas, debut: float, fin: float, densifier: float) -> None:
    temps = 0.5
    basses = [38, 38, 41, 38, 36, 36, 33, 36]
    motif = [62, 65, 69, 65, 64, 65, 62, None]
    k = 0
    while debut + k * temps < fin - 0.05:
        t = debut + k * temps
        dense = t >= densifier
        poser(bas, basse(basses[(k // 2) % 8], temps * 0.5, 0.8), t, 0.45)
        poser(perc, tic(), t + temps / 2, 0.16, pan=0.4)
        if k % 2 == 0:
            poser(perc, grosse_caisse(0.6), t, 0.3 if dense else 0.18)
        if dense and k % 4 == 2:
            poser(perc, clap(0.6), t, 0.1)
        n = motif[k % 8]
        if n:
            poser(m, pluck(n + 12, 0.25, 0.7, 0.35), t, 0.14 if dense else 0.1, pan=-0.3)
        k += 1


def lumiere(m, perc, bas, debut: float, fin: float, plein: float) -> None:
    temps = 60 / 100
    dc = temps / 4
    grille = [(38, [74, 81, 78, 86]), (35, [71, 78, 74, 83]), (31, [79, 83, 86, 91]), (33, [76, 81, 85, 88])]
    i = 0
    while debut + i * temps * 4 < fin - 0.1:
        t0 = debut + i * temps * 4
        racine, arp = grille[i % 4]
        fort = t0 >= plein
        for pos in range(16):
            x = t0 + pos * dc
            if x >= fin:
                break
            poser(perc, shaker(1.0 if pos % 4 == 2 else 0.5), x, 0.08 if fort else 0.05, pan=0.45)
            if fort and pos % 4 == 0:
                poser(perc, grosse_caisse(0.8), x, 0.38)
            if fort and pos in (4, 12):
                poser(perc, clap(0.7), x, 0.12)
            if pos in (0, 3, 6, 8, 10, 12, 14):
                poser(m, kalimba(arp[pos % 4], 0.8), x, 0.22, pan=-0.3 + 0.08 * (pos % 8))
        for pos, d in ((0, 3), (6, 2), (10, 2), (13, 2)):
            if t0 + pos * dc < fin:
                poser(bas, basse(racine, d * dc * 0.9), t0 + pos * dc, 0.36 if fort else 0.25)
        poser(m, nappe([racine + 24, racine + 28, racine + 31], min(temps * 4, fin - t0), 0.5), t0, 0.3)
        i += 1


def main(plan_json: str, dossier_voix: str, sortie: str) -> None:
    plan = json.load(open(plan_json))
    duree, S = plan['duree'], plan['sections']
    m, perc, bas, sfx = piste(duree), piste(duree), piste(duree), piste(duree)

    # ouverture et carte de fin : nappe de ré majeur
    poser(m, nappe([62, 66, 69, 74], S['intro'][1] + 0.4, 0.6), 0.0, 0.35)
    tension(m, perc, bas, S['accroche'][0], S['bascule'][0], densifier=S['souci'][0])
    lumiere(m, perc, bas, S['bascule'][0], S['fin'][0] + 0.3, plein=S['pro1'][0] + 0.9)
    t_fin = S['fin'][0]
    poser(m, nappe([50, 62, 66, 69, 74], duree - t_fin - 0.8, 0.7), t_fin, 0.4)
    for k, n in enumerate((74, 78, 81, 86, 90)):
        poser(m, kalimba(n, 0.8, 2.5), t_fin + 0.9 + k * 0.22, 0.2, pan=-0.4 + 0.2 * k)
    poser(bas, basse(38, 3.0, 0.8), t_fin, 0.35)

    sons = {
        'pop': (pop(1.2), 0.22), 'souffle': (souffle(0.6), 0.16), 'montee': (souffle(0.9, True), 0.3),
        'impact': (impact(), 0.42), 'erreur': (erreur(), 0.13), 'imprimante': (imprimante(1.7), 0.28),
        'papier': (papier(), 0.4), 'ding': (ding(), 0.42), 'signature': (signature(), 0.38),
    }
    for r in plan['reperes']:
        son, gain = sons[r['son']]
        poser(sfx, son, r['t'], gain)

    musique = reverb(m, 1.4, 0.18) + reverb(perc, 0.6, 0.05) + bas
    mixer(plan, dossier_voix, musique, reverb(sfx, 0.6, 0.08) * 0.8, sortie)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3])
