"""Icônes de PressLink Pro (le logiciel des pressings) : tuile nuit, pour ne pas la confondre avec l'application client (tuile bleue)."""
from logo import *
from geo import capsule, arc, ring
from icones import placed, tile

OUT = "../icones/pro"
P, L = symbole()
Ps, Ls = symbole_petit()

def pro(x, y, s):
    """Le mot « PRO » au trait rond (même dessin que le nom), coin haut-gauche (x, y), hauteur de capitale 140·s."""
    h = 19
    parts = [capsule(0, 0, 0, 140, h), capsule(0, 0, 40, 0, h), arc(40, 40, 40, -90, 90, h), capsule(40, 80, 0, 80, h)]  # P
    parts += [capsule(124, 0, 124, 140, h), capsule(124, 0, 164, 0, h), arc(164, 40, 40, -90, 90, h), capsule(164, 80, 124, 80, h), capsule(160, 80, 208, 140, h)]  # R
    parts += [ring(306, 70, 70, h)]  # O
    return shift(" ".join(parts), x, y, s)

PRO_W = 388  # largeur du mot au trait (de -12 à 376)

def tuile_pro(rx=0, echelle=0.6, avec_mot=True):
    groups = [tile(NUIT, rx)]
    if not avec_mot:
        return groups + placed(P, L, BLANC, CLAIR, echelle)
    groups += placed(P, L, BLANC, CLAIR, echelle, cy=108)
    s = 0.155
    groups.append((CLAIR, pro(128 - (PRO_W / 2 - 12) * s, 190, s)))
    return groups

files = {
    "icone-pro.svg": tuile_pro(),
    "icone-pro-arrondie.svg": tuile_pro(56),
    "icone-pro-maskable.svg": [tile(NUIT)] + placed(P, L, BLANC, CLAIR, 0.46, cy=114) + [(CLAIR, pro(128 - (PRO_W / 2 - 12) * 0.12, 174, 0.12))],
    "favicon-pro.svg": [tile(NUIT, 56)] + placed(Ps, Ls, BLANC, CLAIR, 0.8),
}
for name, groups in files.items():
    open(f"{OUT}/{name}", "w").write(to_svg("0 0 256 256", groups, "PressLink Pro"))
print("ok")
