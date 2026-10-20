"""Prépare un tournage pour le montage.

Usage : python preparer.py <dossier_tournage> [largeur=780]
- app.mp4 : les images du tournage à cadence fixe (30 i/s), chaque image
  restant affichée jusqu'à la suivante ;
- barre.json : la couleur du haut de l'écran au fil du temps, pour dessiner
  une barre d'état assortie au-dessus de l'application.
"""
import json
import pathlib
import subprocess
import sys

from PIL import Image


def main(dossier: str, largeur: str = '780') -> None:
    d = pathlib.Path(dossier)
    rec = json.loads((d / 'enregistrement.json').read_text())
    images = [i for i in rec['images'] if i['t'] >= 0]
    lignes = ['ffconcat version 1.0']
    for k, im in enumerate(images):
        fin = images[k + 1]['t'] if k + 1 < len(images) else rec['duree']
        debut = 0 if k == 0 else im['t']
        lignes += [f"file 'images/{im['f']}'", f'duration {max(0.001, fin - debut):.4f}']
    lignes.append(f"file 'images/{images[-1]['f']}'")
    (d / 'liste.ffconcat').write_text('\n'.join(lignes))
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', str(d / 'liste.ffconcat'),
                    '-t', f"{rec['duree']:.3f}", '-vf', f'fps=30,scale={largeur}:-2:flags=lanczos,format=yuv420p', '-c:v', 'libx264', '-crf', '14', '-preset', 'medium',
                    str(d / 'app.mp4')], check=True)
    couleurs = []
    for im in images:
        px = Image.open(d / 'images' / im['f']).convert('RGB').getpixel((4, 4))
        if not couleurs or couleurs[-1]['c'] != px:
            couleurs.append({'t': round(im['t'], 3), 'c': px})
    (d / 'barre.json').write_text(json.dumps(couleurs))
    print('app.mp4 prêt,', len(couleurs), 'changements de couleur de barre')


if __name__ == '__main__':
    main(*sys.argv[1:3])
