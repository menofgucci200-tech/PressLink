"""Sons des maquettes motion v2 (Plissé, Le Fil, Abidjan).

Usage : python v2_sons.py <dossier_v2>
Tout est synthétisé : bruitages de tissu et de vapeur, notes de kora,
ambiances de la ville d'Abidjan de l'aube à la nuit, boucles musicales.
"""
import sys

from audio import *  # noqa: F403
from site_sons import boucle, ecrire


# ---------------------------------------------------------------- bruitages
def froisse(duree: float = 0.5) -> np.ndarray:
    """Tissu qu'on froisse ou qu'on secoue : crépitements filtrés."""
    n = int(duree * SR)
    b = bruit(n) * (rng.random(n) < 0.08)
    s = filtre(b, 1800, 7000) * 3
    return s * np.sin(np.pi * np.linspace(0, 1, n)) ** 0.7


def swish(duree: float = 0.45) -> np.ndarray:
    return souffle(duree) * 1.2


def basse_seche() -> np.ndarray:
    n = int(0.7 * SR)
    t = np.arange(n) / SR
    s = np.sin(2 * np.pi * np.cumsum(55 + 90 * np.exp(-t / 0.02)) / SR) * env(n, 0.001, 0.25)
    return np.tanh(2 * s)


def jet_vapeur(duree: float = 1.1) -> np.ndarray:
    n = int(duree * SR)
    t = np.linspace(0, 1, n)
    e = np.clip(t / 0.03, 0, 1) * np.exp(-t * 2.2)
    return filtre(bruit(n), 1500, 11000) * e


def kora(midi: float, duree: float = 2.6) -> np.ndarray:
    """Corde de kora : pincement brillant, harmoniques, résonance de calebasse."""
    s = pluck(midi, duree, 1.0, 0.85)
    h = pluck(midi + 12, duree * 0.6, 1.0, 0.9)
    s[: len(h)] += 0.35 * h
    n = len(s)
    t = np.arange(n) / SR
    s += 0.12 * np.sin(2 * np.pi * hz(midi - 12) * t) * env(n, 0.01, 0.4)
    return s


# ---------------------------------------------------------------- ambiances d'Abidjan
def oiseaux(duree: float) -> np.ndarray:
    s = piste(duree)
    for _ in range(int(duree * 3)):
        t0 = rng.uniform(0, duree - 0.5)
        f0 = rng.uniform(2500, 4200)
        for k in range(rng.integers(2, 6)):
            d = rng.uniform(0.05, 0.12)
            ph = glisse(f0, f0 * rng.uniform(1.2, 1.6), d, 1.5)
            m = len(ph)
            poser(s, np.sin(ph) * env(m, 0.005, d / 2.5), t0 + k * d * 1.3, rng.uniform(0.05, 0.15), pan=rng.uniform(-0.8, 0.8))
    poser(s, np.stack([filtre(bruit(int(duree * SR)), 200, 900) * 0.02] * 2, 1), 0)  # brise
    return s


def ville(duree: float) -> np.ndarray:
    n = int(duree * SR)
    s = piste(duree)
    rumeur = filtre(np.cumsum(bruit(n)) / 300, 40, 400) + filtre(bruit(n), 300, 1200) * 0.15
    poser(s, np.stack([rumeur, np.roll(rumeur, 2400)], 1) * 0.35, 0)
    for _ in range(int(duree / 2.5)):
        poser(s, klaxon(rng.uniform(0.12, 0.35)), rng.uniform(0, duree - 1), rng.uniform(0.04, 0.12), pan=rng.uniform(-0.9, 0.9))
    for _ in range(int(duree * 1.5)):  # voix lointaines du marché
        d = rng.uniform(0.15, 0.4)
        m = int(d * SR)
        f = rng.uniform(140, 260)
        v = np.sin(2 * np.pi * f * np.arange(m) / SR * (1 + 0.05 * np.sin(np.arange(m) / SR * 30))) * env(m, 0.03, d / 2)
        poser(s, filtre(v, 200, 1500), rng.uniform(0, duree - 0.5), 0.03, pan=rng.uniform(-0.7, 0.7))
    return s


def maquis(duree: float) -> np.ndarray:
    """Musique d'un maquis au loin : groove filtré, comme derrière un mur."""
    from site_sons import soleil
    m = soleil(duree)
    m = np.stack([filtre(m[:, c], haut=900) for c in range(2)], 1) * 0.6
    m[: len(m)] += np.stack([filtre(bruit(len(m)), 300, 1500) * 0.02] * 2, 1)
    return m


def nuit(duree: float) -> np.ndarray:
    n = int(duree * SR)
    s = piste(duree)
    t = np.arange(n) / SR
    for f, pan in ((4300, -0.6), (4700, 0.5), (5100, 0.1)):
        cri = np.sin(2 * np.pi * f * t) * ((t * 22 + pan) % 1 < 0.4) * (0.5 + 0.5 * np.sin(2 * np.pi * 0.7 * t + pan * 3) > 0.3)
        poser(s, filtre(cri, 3000, 7000) * 0.05, 0, pan=pan)
    poser(s, np.stack([filtre(bruit(n), 100, 500) * 0.015] * 2, 1), 0)
    return s


# ---------------------------------------------------------------- boucles
def plisse_boucle(duree: float) -> np.ndarray:
    """Pulsation sombre et minimale (110 BPM) : sub, charleston, accords filtrés."""
    dc = 60 / 110 / 4
    m, perc, bas = piste(duree), piste(duree), piste(duree)
    grille = [(33, [57, 60, 64]), (36, [55, 60, 64]), (31, [55, 59, 62]), (29, [53, 57, 60])]
    for i in range(8):
        t0 = i * 16 * dc
        racine, accord = grille[i % 4]
        for pos in range(16):
            t = t0 + pos * dc
            if pos in (0, 6, 10):
                poser(perc, grosse_caisse(0.9, profond=True), t, 0.5)
            if pos % 2 == 1:
                poser(perc, charleston(False, 0.8), t, 0.07, pan=0.3)
            if pos in (4, 12):
                poser(perc, clap(0.6), t, 0.1)
        for pos in (0, 3, 6, 10, 14):
            poser(bas, basse(racine, dc * 1.5, 0.9), t0 + pos * dc, 0.35)
        poser(m, nappe(accord, 16 * dc, 0.5, 1200), t0, 0.35)
        if i >= 4:
            for pos in (2, 7, 11):
                poser(m, stab([n + 12 for n in accord], 0.6), t0 + pos * dc, 0.06)
    return reverb(m, 1.6, 0.25) + reverb(perc, 0.6, 0.05) + bas


def fil_boucle(duree: float) -> np.ndarray:
    """Kora en arpège lent (pentatonique de sol) sur un bourdon chaud."""
    m = piste(duree)
    notes = [67, 71, 74, 79, 76, 74, 71, 69, 67, 74, 79, 83, 81, 79, 76, 74]
    pas = 60 / 76 / 2
    for i in range(int(duree / pas)):
        if i % 16 in (7, 15):
            continue
        poser(m, kora(notes[i % 16], 2.4), i * pas, 0.22, pan=-0.3 + 0.6 * ((i % 4) / 3))
    for k, racine in enumerate((43, 40, 36, 38)):
        poser(m, nappe([racine + 12, racine + 19, racine + 24], duree / 4, 0.5, 900), k * duree / 4, 0.45)
    return reverb(m, 2.2, 0.3)


def main(v2: str) -> None:
    s = f'{v2}/commun/sons'
    for nom, son in {
        'froisse': froisse(0.6), 'swish': swish(), 'basse': basse_seche(), 'vapeur': jet_vapeur(),
        'pop': pop(), 'clic': clic(), 'signature': signature(), 'souffle': souffle(0.7), 'montee': souffle(1.2, True),
        'impact': impact(), 'ding': ding(), 'klaxon': np.concatenate([klaxon(0.22), np.zeros(int(0.08 * SR)), klaxon(0.32)]),
        'etincelles': etincelles(1.4),
    }.items():
        ecrire(reverb(np.stack([son, son], 1), 0.6, 0.08), f'{s}/{nom}.mp3', 0.8)
    for i, m in enumerate((55, 57, 59, 62, 64, 67, 69, 71, 74, 76, 79, 81)):
        ecrire(reverb(np.stack([kora(m)] * 2, 1), 1.4, 0.2), f'{s}/kora-{i}.mp3', 0.7)
    ecrire(boucle(plisse_boucle, 8 * 16 * 60 / 110 / 4), f'{v2}/1-plisse/audio/musique.mp3', 0.55, '128k')
    ecrire(boucle(fil_boucle, 16 * 60 / 76 / 2 * 4), f'{v2}/2-fil/audio/musique.mp3', 0.5, '128k')
    for nom, f, d in (('aube', oiseaux, 16.0), ('jour', ville, 16.0), ('soir', maquis, 19.2), ('nuit', nuit, 12.0)):
        ecrire(boucle(f, d), f'{v2}/3-abidjan/audio/{nom}.mp3', 0.6, '128k')


if __name__ == '__main__':
    main(sys.argv[1])
