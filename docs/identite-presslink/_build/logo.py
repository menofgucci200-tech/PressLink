"""Masters du logo PressLink, piste A « L'accroche » : le L de Link glissé dans la boucle du P."""
from geo import capsule, arc, ellarc, disc, f, to_svg

NUIT, BLEU, CLAIR, BLANC, NOIR = "#0F172A", "#2563EB", "#60A5FA", "#FFFFFF", "#0F172A"

# ------------------------------------------------------------------ symbole
def symbole(w=28, gap_bar=12, gap_top=8, dx=-16, dy=6):
    """Renvoie (chemin du P, chemin du L) sur une grille de 256.

    Hampe x=72 de 40 à 204 ; boucle de rayon 48 centrée (136, 88) → barre basse y=136.
    Le L (x=136) a sa pointe dans la contre-forme et ressort sous la barre (il passe dessous).
    """
    h = w / 2
    X = lambda x: x + dx
    Y = lambda y: y + dy
    x0, top, bar, cx, bottom = 72, 40, 136, 136, 204
    r = (bar - top) / 2
    R, q = r + h, r - h
    p = (f"M{f(X(x0-h))} {f(Y(top))} A{f(h)} {f(h)} 0 0 1 {f(X(x0))} {f(Y(top-h))} H{f(X(cx))} "
         f"A{f(R)} {f(R)} 0 0 1 {f(X(cx))} {f(Y(bar+h))} H{f(X(x0+h))} V{f(Y(bottom))} "
         f"A{f(h)} {f(h)} 0 0 1 {f(X(x0-h))} {f(Y(bottom))} Z "
         f"M{f(X(x0+h))} {f(Y(top+h))} V{f(Y(bar-h))} H{f(X(cx))} A{f(q)} {f(q)} 0 0 0 {f(X(cx))} {f(Y(top+h))} Z")
    # pointe du L : capsule dans la contre-forme
    s_top = top + h + gap_top + h
    s_bot = bar - h - gap_bar - h
    pointe = capsule(X(136), Y(s_top), X(136), Y(max(s_bot, s_top + 0.01)), h)
    # le L sous la barre
    l_top = bar + h + gap_bar + h
    l = capsule(X(136), Y(l_top), X(136), Y(bottom), h) + " " + capsule(X(136), Y(bottom), X(216), Y(bottom), h)
    return p, pointe + " " + l

def symbole_petit():
    """Dessin pour 16–32 px : trait plus épais, pointe réduite à une pastille, jours élargis."""
    return symbole(w=36, gap_bar=14, gap_top=6, dx=-16, dy=6)

# ------------------------------------------------------------------ mot « PressLink »
# Grille : capitales 60..200 (axes), hauteur d'x 110, ligne de base 200 ; trait 24.
H = 12
def g_P(x):  return [capsule(x, 60, x, 200, H), capsule(x, 60, x+40, 60, H), arc(x+40, 100, 40, -90, 90, H), capsule(x+40, 140, x, 140, H)], 80
def g_r(x):  return [capsule(x, 110, x, 200, H), arc(x+40, 150, 40, 180, 270, H)], 40
def g_e(x):  return [capsule(x, 155, x+90, 155, H), arc(x+45, 155, 45, 45, 360, H)], 90
def g_s(x):  return [ellarc(x+33, 132.5, 33, 22.5, 90, 330, H), ellarc(x+33, 177.5, 33, 22.5, -90, 150, H)], 66
def g_L(x):  return [capsule(x, 60, x, 200, H), capsule(x, 200, x+66, 200, H)], 66
def g_i(x):  return [capsule(x, 110, x, 200, H), disc(x, 68, H + 2)], 0
def g_n(x):  return [capsule(x, 110, x, 200, H), arc(x+40, 150, 40, 180, 360, H), capsule(x+80, 150, x+80, 200, H)], 80
def g_k(x):  return [capsule(x, 60, x, 200, H), capsule(x, 174, x+58, 116, H), capsule(x+24, 150, x+74, 200, H)], 74

# (glyphe, espace optique avant la lettre suivante, entre bords extérieurs)
SEQ = [(g_P, 24), (g_r, 10), (g_e, 16), (g_s, 15), (g_s, 24), (g_L, 16), (g_i, 30), (g_n, 30), (g_k, 0)]

def mot(x0=26.0):
    """Renvoie (chemin « Press », chemin « Link », largeur totale)."""
    press, link = [], []
    x = x0 + H
    for idx, (g, gap) in enumerate(SEQ):
        parts, width = g(x)
        (press if idx < 5 else link).extend(parts)
        x = x + width + H + gap + H
    right = x - gap - 2 * H + H  # bord droit du dernier glyphe
    return " ".join(press), " ".join(link), right + 26

# ------------------------------------------------------------------ assemblages
def shift(d, dx, dy, s=1.0):
    """Translate/met à l'échelle un chemin absolu (M L H V A Z uniquement)."""
    import re
    out, tokens = [], re.findall(r"[MLHVAZ]|-?\d*\.?\d+", d)
    i, cmd = 0, None
    def num():
        nonlocal i
        v = float(tokens[i]); i += 1; return v
    while i < len(tokens):
        t = tokens[i]
        if t in "MLHVAZ":
            cmd = t; i += 1; out.append(t)
            if t == "Z":
                continue
        if cmd in "ML":
            x, y = num(), num(); out.append(f"{f(x*s+dx)} {f(y*s+dy)}")
        elif cmd == "H":
            out.append(f(num()*s+dx))
        elif cmd == "V":
            out.append(f(num()*s+dy))
        elif cmd == "A":
            rx, ry, rot, la, sw, x, y = num(), num(), num(), num(), num(), num(), num()
            out.append(f"{f(rx*s)} {f(ry*s)} {f(rot)} {int(la)} {int(sw)} {f(x*s+dx)} {f(y*s+dy)}")
    return " ".join(out)
