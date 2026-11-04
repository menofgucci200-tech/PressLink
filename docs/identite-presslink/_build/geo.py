"""Géométrie du logo PressLink : traits épais à bouts ronds convertis en formes pleines.

Chaque élément (segment, arc, anneau) devient un contour fermé tourné dans le même
sens ; avec fill-rule="nonzero" les recouvrements s'unissent et les trous des anneaux
(tournés à l'envers) restent vides. Aucun trait, aucun texte dans les fichiers finaux.
"""
import math

def f(v):
    s = f"{v:.2f}".rstrip("0").rstrip(".")
    return "0" if s in ("-0", "") else s

def capsule(x1, y1, x2, y2, h):
    """Segment épais (demi-épaisseur h) à bouts ronds, sens horaire."""
    dx, dy = x2 - x1, y2 - y1
    L = math.hypot(dx, dy) or 1e-9
    nx, ny = -dy / L * h, dx / L * h          # normale (à gauche du sens de parcours, écran)
    a = (x1 - nx, y1 - ny); b = (x2 - nx, y2 - ny); c = (x2 + nx, y2 + ny); d = (x1 + nx, y1 + ny)
    return (f"M{f(a[0])} {f(a[1])} L{f(b[0])} {f(b[1])} A{f(h)} {f(h)} 0 0 1 {f(c[0])} {f(c[1])} "
            f"L{f(d[0])} {f(d[1])} A{f(h)} {f(h)} 0 0 1 {f(a[0])} {f(a[1])} Z")

def _pt(cx, cy, r, deg):
    t = math.radians(deg)
    return cx + r * math.cos(t), cy + r * math.sin(t)

def arc(cx, cy, r, a1, a2, h):
    """Arc épais de a1 à a2 (degrés, sens horaire écran si a2 > a1), bouts ronds."""
    if a2 < a1:
        a1, a2 = a2, a1
    span = a2 - a1
    large = 1 if span > 180 else 0
    o1 = _pt(cx, cy, r + h, a1); o2 = _pt(cx, cy, r + h, a2)
    i2 = _pt(cx, cy, r - h, a2); i1 = _pt(cx, cy, r - h, a1)
    return (f"M{f(o1[0])} {f(o1[1])} A{f(r+h)} {f(r+h)} 0 {large} 1 {f(o2[0])} {f(o2[1])} "
            f"A{f(h)} {f(h)} 0 0 1 {f(i2[0])} {f(i2[1])} "
            f"A{f(r-h)} {f(r-h)} 0 {large} 0 {f(i1[0])} {f(i1[1])} "
            f"A{f(h)} {f(h)} 0 0 1 {f(o1[0])} {f(o1[1])} Z")

def ellarc(cx, cy, rx, ry, a1, a2, h, steps=24):
    """Arc d'ellipse épais (pour le s), polygone fin + bouts ronds."""
    if a2 < a1:
        a1, a2 = a2, a1
    pts_o, pts_i = [], []
    for k in range(steps + 1):
        t = math.radians(a1 + (a2 - a1) * k / steps)
        x, y = cx + rx * math.cos(t), cy + ry * math.sin(t)
        # normale à l'ellipse
        nx, ny = math.cos(t) / rx, math.sin(t) / ry
        n = math.hypot(nx, ny); nx, ny = nx / n, ny / n
        pts_o.append((x + nx * h, y + ny * h)); pts_i.append((x - nx * h, y - ny * h))
    d = f"M{f(pts_o[0][0])} {f(pts_o[0][1])} " + " ".join(f"L{f(x)} {f(y)}" for x, y in pts_o[1:])
    d += f" A{f(h)} {f(h)} 0 0 1 {f(pts_i[-1][0])} {f(pts_i[-1][1])} "
    d += " ".join(f"L{f(x)} {f(y)}" for x, y in reversed(pts_i[:-1]))
    d += f" L{f(pts_i[0][0])} {f(pts_i[0][1])} A{f(h)} {f(h)} 0 0 1 {f(pts_o[0][0])} {f(pts_o[0][1])} Z"
    return d

def ring(cx, cy, r, h):
    """Anneau : cercle extérieur horaire + trou antihoraire."""
    R, q = r + h, r - h
    return (f"M{f(cx+R)} {f(cy)} A{f(R)} {f(R)} 0 1 1 {f(cx-R)} {f(cy)} A{f(R)} {f(R)} 0 1 1 {f(cx+R)} {f(cy)} Z "
            f"M{f(cx+q)} {f(cy)} A{f(q)} {f(q)} 0 1 0 {f(cx-q)} {f(cy)} A{f(q)} {f(q)} 0 1 0 {f(cx+q)} {f(cy)} Z")

def disc(cx, cy, r):
    return f"M{f(cx+r)} {f(cy)} A{f(r)} {f(r)} 0 1 1 {f(cx-r)} {f(cy)} A{f(r)} {f(r)} 0 1 1 {f(cx+r)} {f(cy)} Z"

def bowl(x0, top, bottom, h):
    """Boucle de P : barre haute de x0 à cx, demi-cercle, barre basse revenant à x0 (forme pleine avec contre-forme)."""
    r = (bottom - top) / 2
    cx, cy = None, top + r
    return r, cy

def p_shape(x0, top, bar, h, cx):
    """Le P du logo en un contour : hampe arrondie, coin haut arrondi, boucle et contre-forme."""
    r = (bar - top) / 2
    cy = top + r
    R, q = r + h, r - h
    outer = (f"M{f(x0-h)} {f(top)} A{f(h)} {f(h)} 0 0 1 {f(x0)} {f(top-h)} H{f(cx)} "
             f"A{f(R)} {f(R)} 0 0 1 {f(cx)} {f(bar+h)} H{f(x0+h)} V{{STEM_END}} ")
    hole = (f"M{f(x0+h)} {f(top+h)} V{f(bar-h)} H{f(cx)} A{f(q)} {f(q)} 0 0 0 {f(cx)} {f(top+h)} Z")
    return outer, hole, cy, r

def to_svg(view, groups, title):
    body = "\n".join(f'<path fill="{c}" d="{d}"/>' for c, d in groups if d)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{view}" role="img" aria-labelledby="t">'
            f'<title id="t">{title}</title>\n{body}\n</svg>\n')
