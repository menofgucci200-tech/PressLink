from logo import *

OUT = "../logo"
def write(name, svg):
    open(f"{OUT}/{name}", "w").write(svg)

palettes = {
    "couleur": (NUIT, BLEU),
    "negatif": (BLANC, CLAIR),     # sur fond nuit / foncé
    "noir": (NOIR, NOIR),
    "blanc": (BLANC, BLANC),
}

P, L = symbole()
Ps, Ls = symbole_petit()
wp, wl, WW = mot()

for key, (c1, c2) in palettes.items():
    write(f"presslink-symbole-{key}.svg", to_svg("0 0 256 256", [(c1, P), (c2, L)], "PressLink"))
    write(f"presslink-symbole-petit-{key}.svg", to_svg("0 0 256 256", [(c1, Ps), (c2, Ls)], "PressLink"))
    write(f"presslink-nom-{key}.svg", to_svg(f"0 0 {f(WW)} 256", [(c1, wp), (c2, wl)], "PressLink"))
    # horizontal : symbole (hauteur visuelle 192) + nom à l'échelle (capitales ≈ 0,8 × symbole)
    s = 0.92
    sym_w = 266
    hp = shift(wp, sym_w - 26 * s, (128 - 130 * s), s)
    hl = shift(wl, sym_w - 26 * s, (128 - 130 * s), s)
    width = sym_w + (WW - 52) * s + 42
    write(f"presslink-horizontal-{key}.svg", to_svg(f"0 0 {f(width)} 256", [(c1, P), (c2, L), (c1, hp), (c2, hl)], "PressLink"))
    # empilé : symbole au-dessus, nom centré dessous
    s2 = 0.6
    ww = (WW - 52) * s2
    total_w = max(256, ww + 48)
    sx = (total_w - 256) / 2
    sp, sl = shift(P, sx, 0), shift(L, sx, 0)
    vp = shift(wp, (total_w - ww) / 2 - 26 * s2, 266 - 48 * s2, s2)
    vl = shift(wl, (total_w - ww) / 2 - 26 * s2, 266 - 48 * s2, s2)
    write(f"presslink-empile-{key}.svg", to_svg(f"0 0 {f(total_w)} {f(266 + 164 * s2 + 32)}", [(c1, sp), (c2, sl), (c1, vp), (c2, vl)], "PressLink"))
print("masters ok, largeur du nom", round(WW))
