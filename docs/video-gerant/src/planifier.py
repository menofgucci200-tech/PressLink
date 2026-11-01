"""Construit timeline.json à partir des durées mesurées des répliques.

Usage : python planifier.py <repliques.json> <timeline.json>

Chaque réplique commence après la précédente (courte respiration dans une
scène, plus longue au changement de scène) ; chaque scène commence un peu
avant sa première réplique et finit juste avant la scène suivante. La vidéo
(video.html) cale toutes ses animations sur ces débuts de réplique.
"""
import json
import sys

SCENES = [
    ('accroche', ['a1', 'a2']),
    ('probleme', ['b1', 'b2', 'b3', 'b4']),
    ('solution', ['c1', 'c2']),
    ('outil', ['d1', 'd2', 'd3', 'd4']),
    ('appli', ['e1']),
    ('fin', ['f1', 'f2']),
]
DEBUT = 0.5          # silence avant la première réplique
RESPIRATION = 0.3    # entre deux répliques d'une même scène
CHANGEMENT = 0.75    # entre la dernière réplique d'une scène et la première de la suivante
AVANCE = 0.35        # la scène apparaît un peu avant sa première réplique
FIN = 3.3            # l'écran final reste affiché après la dernière réplique


def main(repliques_path: str, sortie: str) -> None:
    durees = {r['cle']: r['duree'] for r in json.load(open(repliques_path))}
    voix, scenes, t = [], [], DEBUT
    for i, (scene, cles) in enumerate(SCENES):
        if i > 0:
            t += CHANGEMENT - RESPIRATION
        debut_scene = 0.0 if i == 0 else round(t - AVANCE, 2)
        for cle in cles:
            voix.append({'cle': cle, 'debut': round(t, 2)})
            t += durees[cle] + RESPIRATION
        scenes.append({'id': scene, 'debut': debut_scene})
    duree = round(t - RESPIRATION + FIN, 1)
    for i, s in enumerate(scenes):
        s['fin'] = scenes[i + 1]['debut'] if i + 1 < len(scenes) else duree
    debuts = {v['cle']: v['debut'] for v in voix}
    timeline = {
        'fps': 30,
        'duree': duree,
        'groove': scenes[2]['debut'],  # la musique démarre avec la solution
        'voix': voix,
        'scenes': scenes,
        'sons': {
            'souffles': [s['debut'] for s in scenes[1:]],
            'signature_sonore': [round(debuts['c2'] + 0.05, 2), round(debuts['f1'] + 0.05, 2)],
            'notification': [round(debuts['e1'] + 2.6, 2)],
            'pops': [round(debuts[k] + 0.05, 2) for k in ['b1', 'b2', 'b3', 'b4', 'd1', 'd2', 'd3', 'd4', 'f2']],
        },
    }
    with open(sortie, 'w') as f:
        json.dump(timeline, f, ensure_ascii=False, indent=2)
    print('durée', duree, 's ;', ', '.join(f"{v['cle']}@{v['debut']}" for v in voix))


if __name__ == '__main__':
    main(*sys.argv[1:3])
