"""Musiques en boucle et bruitages des maquettes du site vitrine.

Usage : python site_sons.py <dossier_site>
Écrit <site>/commun/sons/*.mp3 (bruitages partagés) et la boucle musicale
de chaque orientation (<site>/<maquette>/audio/musique*.mp3). Les boucles
se raccordent sans couture : la queue de réverbération est repliée au début.
"""
import subprocess
import sys
import tempfile

from scipy.io import wavfile

from audio import *  # noqa: F403


def ecrire(son: np.ndarray, chemin: str, gain: float = 0.8, debit: str = '96k') -> None:
    if son.ndim == 1:
        son = np.stack([son, son], axis=1)
    son = son / max(1e-9, np.max(np.abs(son))) * gain
    with tempfile.NamedTemporaryFile(suffix='.wav') as tmp:
        wavfile.write(tmp.name, SR, son.astype(np.float32))
        subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-i', tmp.name,
                        '-ar', '44100', '-b:a', debit, chemin], check=True)
    print('→', chemin)


def boucle(composer, longueur: float) -> np.ndarray:
    """Compose sur longueur + 3 s, puis replie la fin sur le début."""
    x = composer(longueur + 3.0)
    n = int(longueur * SR)
    out = x[:n].copy()
    queue = x[n:n + int(3.0 * SR)]
    out[: len(queue)] += queue
    return out


# ---------------------------------------------------------------- boucles
def bouba(duree: float) -> np.ndarray:
    dc = 60 / 104 / 4
    grille = [(41, [53, 57, 60]), (38, [50, 53, 57]), (34, [46, 50, 53]), (36, [48, 52, 55])]
    melodie = [(0, 77), (3, 81), (6, 84), (8, 81), (10, 79), (12, 77), (14, 74), (16, 77), (19, 79), (22, 81), (24, 84), (26, 86), (28, 84), (30, 81)]
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    for i in range(8):
        t0 = i * 16 * dc
        racine, accord = grille[i % 4]
        for pos in range(16):
            t = t0 + pos * dc
            if pos % 4 == 0:
                poser(perc, grosse_caisse(0.8), t, 0.45)
            if pos in (4, 12):
                poser(perc, clap(0.8), t, 0.16)
            poser(perc, shaker(1.0 if pos % 2 else 0.5), t, 0.07, pan=0.5)
        for pos, o in ((0, 0), (3, 12), (6, 0), (8, 7), (10, 12), (12, 0), (14, 7)):
            poser(bas, pluck(racine + 12 + o, 0.35, 0.8, 0.3), t0 + pos * dc, 0.5)
            poser(bas, basse(racine + o, dc * 1.6, 0.6), t0 + pos * dc, 0.22)
        for pos in (2, 6, 10, 14):
            for k, n in enumerate(accord):
                poser(m, marimba(n + 12, 0.6), t0 + pos * dc, 0.13, pan=-0.25 + 0.25 * k)
        if i >= 4:
            for pos, n in melodie:
                if (i % 2) * 16 <= pos < (i % 2 + 1) * 16:
                    poser(m, marimba(n, 0.8), t0 + (pos % 16) * dc, 0.2, pan=0.2)
    return reverb(m, 1.5, 0.2) + reverb(perc, 0.7, 0.05) + bas


def appli(duree: float) -> np.ndarray:
    dc = 0.5 / 4
    grille = [(45, [57, 60, 64, 67]), (41, [57, 60, 64, 65]), (48, [55, 60, 64, 67]), (43, [55, 59, 62, 67])]
    log = [(0, 0), (3, 0), (6, 7), (10, 12), (11, 10), (14, 7)]
    riff = [(0, 76), (2, 79), (3, 81), (6, 79), (8, 76), (10, 74), (11, 76), (14, 72)]
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    for i in range(8):
        t0 = i * 2.0
        racine, accord = grille[i % 4]
        for pos in range(16):
            t = t0 + pos * dc
            if pos % 4 == 0:
                poser(perc, grosse_caisse(1.0, profond=True), t, 0.55)
            if pos % 4 == 2:
                poser(perc, charleston(True, 0.9), t, 0.1, pan=0.25)
            poser(perc, shaker(0.9 if pos % 2 else 0.45), t, 0.06, pan=-0.45)
            if pos in (4, 12):
                poser(perc, clap(1.0), t, 0.16)
        for pos, d in log:
            poser(bas, log_drum(racine + d, 0.9), t0 + pos * dc, 0.45)
        for pos in (2, 6, 10, 13):
            poser(m, stab([n + 12 for n in accord], 0.7), t0 + pos * dc, 0.08)
        if i >= 4:
            for pos, n in riff:
                poser(m, pluck(n - 12, 0.35, 0.8, 0.7), t0 + pos * dc, 0.14, pan=0.35)
        poser(m, nappe(accord, 2.0, 0.5, 1800), t0, 0.3)
    return reverb(m, 1.3, 0.18) + reverb(perc, 0.6, 0.05) + bas


def galere(duree: float) -> np.ndarray:
    temps = 60 / 132
    basses = [38, 45, 38, 45, 41, 45, 40, 37]
    melodie = [62, 65, 69, 65, 64, 61, 62, None, 62, 65, 69, 72, 70, 69, 67, 64]
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    for k in range(32):
        t = k * temps
        poser(bas, basse(basses[k % 8], temps * 0.45, 0.9), t, 0.5)
        poser(perc, tic(), t, 0.22, pan=0.5)
        poser(perc, tic(), t + temps / 2, 0.1, pan=0.5)
        for j in (0, 1):
            n = melodie[(2 * k + j) % 16]
            if n:
                poser(m, pluck(n + 12, 0.28, 0.8, 0.4), t + j * temps / 2, 0.2, pan=-0.3)
        if k % 2 == 1:
            poser(m, marimba(74 if k % 4 == 1 else 73, 0.6), t, 0.12, pan=0.3)
    return reverb(m, 1.2, 0.15) + perc + bas


def soleil(duree: float) -> np.ndarray:
    temps = 60 / 100
    dc = temps / 4
    grille = [(38, [74, 81, 78, 86]), (35, [71, 78, 74, 83]), (31, [79, 83, 86, 91]), (33, [76, 81, 85, 88])]
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    for i in range(8):
        t0 = i * temps * 4
        racine, arp = grille[i % 4]
        for pos in range(16):
            x = t0 + pos * dc
            poser(perc, shaker(1.0 if pos % 4 == 2 else 0.5), x, 0.08, pan=0.45)
            if pos % 8 == 0:
                poser(perc, grosse_caisse(0.8), x, 0.4)
            if pos in (4, 12):
                poser(perc, clap(0.7), x, 0.12)
            if pos in (0, 3, 6, 8, 10, 12, 14):
                poser(m, kalimba(arp[pos % 4], 0.8), x, 0.24, pan=-0.3 + 0.08 * (pos % 8))
        for pos, d in ((0, 3), (6, 2), (10, 2), (13, 2)):
            poser(bas, basse(racine, d * dc * 0.9), t0 + pos * dc, 0.38)
        poser(m, nappe([racine + 24, racine + 28, racine + 31], temps * 4, 0.5), t0, 0.32)
    return reverb(m, 1.3, 0.18) + reverb(perc, 0.6, 0.05) + bas


def main(site: str) -> None:
    s = f'{site}/commun/sons'
    bruitages = {
        'pop': pop(), 'pop-aigu': pop(1.3), 'clic': clic(), 'ding': ding(), 'signature': signature(),
        'souffle': souffle(0.6), 'souffle-montee': souffle(0.9, True), 'boing': boing(), 'trombone': trombone_triste(),
        'tiroir-caisse': tiroir_caisse(), 'erreur': erreur(), 'sonnerie': sonnerie(1.2), 'klaxon': np.concatenate([klaxon(0.22), np.zeros(int(0.08 * SR)), klaxon(0.32)]),
        'bulles': bulles(1.6, 22), 'vapeur': vapeur(1.0), 'etincelles': etincelles(1.2), 'imprimante': imprimante(0.85),
        'papier': papier(), 'impact': impact(), 'tic': tic(),
    }
    for nom, son in bruitages.items():
        ecrire(reverb(np.stack([son, son], 1), 0.6, 0.08), f'{s}/{nom}.mp3', 0.8, '96k')
    ecrire(boucle(bouba, 8 * 16 * 60 / 104 / 4), f'{site}/1-bouba/audio/musique.mp3', 0.55, '128k')
    ecrire(boucle(appli, 16.0), f'{site}/2-appli/audio/musique.mp3', 0.55, '128k')
    ecrire(boucle(galere, 32 * 60 / 132), f'{site}/3-avant-apres/audio/musique-avant.mp3', 0.5, '128k')
    ecrire(boucle(soleil, 8 * 4 * 60 / 100), f'{site}/3-avant-apres/audio/musique-apres.mp3', 0.55, '128k')


if __name__ == '__main__':
    main(sys.argv[1])
