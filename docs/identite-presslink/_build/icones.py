from logo import *

OUT = "../icones"
P, L = symbole()
Ps, Ls = symbole_petit()

def placed(pd, ld, c1, c2, scale, cx=128, cy=128):
    """Place le symbole (centré sur 128,128) à l'échelle voulue."""
    dx, dy = cx - 128 * scale, cy - 128 * scale
    return [(c1, shift(pd, dx, dy, scale)), (c2, shift(ld, dx, dy, scale))]

def tile(fill, rx=0):
    return (fill, f"M{rx} 0 H{256-rx} A{rx} {rx} 0 0 1 256 {rx} V{256-rx} A{rx} {rx} 0 0 1 {256-rx} 256 H{rx} A{rx} {rx} 0 0 1 0 {256-rx} V{rx} A{rx} {rx} 0 0 1 {rx} 0 Z" if rx else "M0 0 H256 V256 H0 Z")

files = {
    # Icône d'application : fond plein (le système arrondit lui-même), P blanc, L nuit.
    "icone-app.svg": [tile(BLEU)] + placed(P, L, BLANC, NUIT, 0.74),
    # Version arrondie, pour les présentations et le site.
    "icone-app-arrondie.svg": [tile(BLEU, 56)] + placed(P, L, BLANC, NUIT, 0.74),
    # Maskable (Android) : le dessin tient dans la zone sûre (cercle de 80 %).
    "icone-maskable.svg": [tile(BLEU)] + placed(P, L, BLANC, NUIT, 0.56),
    # Favicon : dessin « petit », tuile arrondie.
    "favicon.svg": [tile(BLEU, 56)] + placed(Ps, Ls, BLANC, NUIT, 0.8),
    # Pastille des notifications Android : silhouette blanche sur transparent.
    "notification-badge.svg": placed(Ps, Ls, BLANC, BLANC, 1.0),
    # Logo carré (Google, réseaux) : symbole couleur sur blanc.
    "logo-carre.svg": [tile(BLANC)] + placed(P, L, NUIT, BLEU, 0.84),
}
for name, groups in files.items():
    open(f"{OUT}/{name}", "w").write(to_svg("0 0 256 256", groups, "PressLink"))
print("ok")
