"""Charte graphique PressLink : planches 1600 × 1000 (PNG) et un PDF qui les réunit."""
import os, re, subprocess
from logo import *

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "charte")
TMP = os.path.join(HERE, "_html"); os.makedirs(TMP, exist_ok=True)
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
R = "../.."  # racine docs/identite-presslink vue depuis _build/_html

def svg(name, style="", dossier="logo"):
    s = open(os.path.join(HERE, "..", dossier, name)).read()
    s = re.sub(r"<title[^>]*>.*?</title>", "", s)
    return s.replace("<svg ", f'<svg style="{style}" ', 1)

def cmjn(hexa):
    r, g, b = (int(hexa[i:i + 2], 16) / 255 for i in (1, 3, 5))
    k = 1 - max(r, g, b)
    if k >= 1: return "0 · 0 · 0 · 100"
    c, m, y = ((1 - x - k) / (1 - k) for x in (r, g, b))
    return " · ".join(str(round(v * 100)) for v in (c, m, y, k))

def rvb(hexa): return " · ".join(str(int(hexa[i:i + 2], 16)) for i in (1, 3, 5))

STYLE = """
:root{--nuit:#0F172A;--encre:#0B1530;--bleu:#2563EB;--clair:#60A5FA;--vert:#16A34A;--fond:#F5F7FC;--gris:#475569;--trait:#E2E8F0}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--nuit);-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pl{position:relative;width:1600px;height:1000px;overflow:hidden;background:#fff;padding:80px 90px;page-break-after:always}
.t{font-family:'Bricolage Grotesque',sans-serif;letter-spacing:-.035em;line-height:1}
.sur{font-size:15px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--bleu)}
h2{font-family:'Bricolage Grotesque',sans-serif;font-size:56px;font-weight:800;letter-spacing:-.035em;margin-top:14px}
.chapo{font-size:20px;line-height:1.55;color:var(--gris);margin-top:16px;max-width:780px}
.pied{position:absolute;left:90px;right:90px;bottom:40px;display:flex;justify-content:space-between;font-size:13px;color:#94A3B8;font-weight:600}
.case{border:1px solid var(--trait);border-radius:24px;display:grid;place-items:center;position:relative}
.leg{position:absolute;left:20px;bottom:16px;font-size:13px;font-weight:700;color:var(--gris)}
@page{size:1600px 1000px;margin:0}
"""
HEAD = ('<!doctype html><html lang="fr"><head><meta charset="utf-8"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
        'family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
        f'<style>{STYLE}</style></head><body>')

def pied(n): return f'<div class="pied"><span>PressLink · Charte graphique · v1 · novembre 2026</span><span>{n:02d} / 07</span></div>'

PL = []

# 1 — Couverture : le logo et son idée
PL.append(f"""<div class="pl" style="background:var(--encre);color:#fff">
<div style="position:absolute;width:1100px;height:1100px;right:-300px;top:-420px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.55),transparent 65%)"></div>
<p class="sur" style="color:var(--clair);position:relative">Charte graphique · v1</p>
<div style="position:relative;margin-top:150px">{svg('presslink-horizontal-negatif.svg','height:190px;display:block;margin-left:-30px')}</div>
<p class="t" style="position:relative;font-size:44px;font-weight:700;margin-top:60px;max-width:1000px">L'accroche : le L remonte sous la barre du P et vient s'y clipser.</p>
<p style="position:relative;font-size:21px;color:#CBD5E1;line-height:1.55;margin-top:22px;max-width:900px">Le pressing (P) et ses clients (L) sont reliés par un seul geste. Une idée, deux couleurs, un dessin lisible dès 16 pixels.</p>
<div class="pied" style="color:#64748B"><span>PressLink · Charte graphique · v1 · novembre 2026</span><span>01 / 07</span></div></div>""")

# 2 — Versions du logo
cases = [
    ("presslink-horizontal-couleur.svg", "#fff", "Horizontal · couleur (version principale)", "height:120px", "span 2"),
    ("presslink-horizontal-negatif.svg", "var(--encre)", "Horizontal · négatif", "height:120px", "span 2"),
    ("presslink-empile-couleur.svg", "#fff", "Empilé", "height:210px", "span 1"),
    ("presslink-symbole-couleur.svg", "var(--fond)", "Symbole", "height:170px", "span 1"),
    ("presslink-horizontal-noir.svg", "#fff", "Une couleur · noir", "height:70px", "span 1"),
    ("presslink-horizontal-blanc.svg", "var(--bleu)", "Une couleur · blanc", "height:70px", "span 1"),
]
grille = "".join(f'<div class="case" style="background:{bg};grid-column:{col};{"border-color:transparent;" if bg not in ("#fff",) else ""}">{svg(n, st)}<span class="leg" style="{"color:#CBD5E1" if bg in ("var(--encre)","var(--bleu)") else ""}">{leg}</span></div>' for n, bg, leg, st, col in cases)
PL.append(f"""<div class="pl"><p class="sur">Logo</p><h2>Les versions</h2>
<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:290px 360px;gap:24px;margin-top:44px">{grille}</div>{pied(2)}</div>""")

# 3 — Zone de protection et tailles minimales
P, L = symbole()
x = 28  # unité de protection : l'épaisseur du trait
hz = svg("presslink-horizontal-couleur.svg", "height:200px;display:block")
taille = lambda px, f="presslink-symbole-couleur.svg": f'<div style="text-align:center"><div style="height:64px;display:grid;place-items:center">{svg(f, f"width:{px}px;height:{px}px")}</div><p style="font-size:13px;color:var(--gris);font-weight:700;margin-top:8px">{px} px</p></div>'
PL.append(f"""<div class="pl"><p class="sur">Logo</p><h2>Protection et tailles minimales</h2>
<div style="display:grid;grid-template-columns:1.25fr 1fr;gap:50px;margin-top:44px">
 <div class="case" style="height:520px;background:var(--fond);border:0">
  <div style="position:relative;padding:{200*x/256:.1f}px;outline:2px dashed #93C5FD;background:repeating-linear-gradient(45deg,rgba(37,99,235,.06) 0 8px,transparent 8px 16px)">
   <div style="background:var(--fond)">{hz}</div>
   <span style="position:absolute;left:0;top:50%;width:{200*x/256:.1f}px;margin-top:-14px;text-align:center;font:800 18px 'Bricolage Grotesque';color:var(--bleu)">x</span>
   <span style="position:absolute;left:50%;top:0;height:{200*x/256:.1f}px;display:grid;place-items:center;font:800 18px 'Bricolage Grotesque';color:var(--bleu)">x</span>
  </div>
  <span class="leg">x = l'épaisseur du trait du P. Rien ne vient dans cette zone.</span></div>
 <div>
  <p class="t" style="font-size:26px;font-weight:800">Tailles minimales</p>
  <table style="margin-top:18px;border-collapse:collapse;font-size:17px;width:100%">
   {''.join(f'<tr style="border-bottom:1px solid var(--trait)"><td style="padding:13px 0;font-weight:700">{a}</td><td style="color:var(--gris)">{b}</td><td style="color:var(--gris)">{c}</td></tr>' for a,b,c in [("Version","Écran","Impression"),("Horizontal","28 px de haut","10 mm"),("Empilé","64 px de haut","18 mm"),("Symbole","24 px","6 mm"),("Symbole · coupe petite","16 px","4 mm")])}
  </table>
  <p style="font-size:15px;color:var(--gris);line-height:1.55;margin-top:18px">Sous 24 px, on utilise la <b>coupe petite</b> du symbole (traits plus épais, accroche plus grande) : c'est elle qui sert au favicon.</p>
  <div style="display:flex;gap:26px;align-items:flex-end;margin-top:26px">{taille(64)}{taille(32)}{taille(24)}{taille(16,'presslink-symbole-petit-couleur.svg')}</div>
 </div></div>{pied(3)}</div>""")

# 4 — Couleurs
COUL = [
    ("Bleu PressLink", "#2563EB", "≈ Pantone 2728 C", "Le L, la tuile d'application, les liens", "#fff"),
    ("Nuit", "#0F172A", "≈ Pantone Black 6 C", "Le P et « Press », les titres", "#fff"),
    ("Bleu clair", "#60A5FA", "≈ Pantone 284 C", "Le L sur fond sombre", "var(--nuit)"),
    ("Encre", "#0B1530", "≈ Pantone 5395 C", "Fonds sombres (site, cartes)", "#fff"),
    ("Vert action", "#16A34A", "≈ Pantone 7740 C", "Boutons d'action uniquement", "#fff"),
    ("Fond clair", "#F5F7FC", "—", "Fonds clairs", "var(--nuit)"),
]
tuiles = "".join(f'''<div style="border-radius:22px;overflow:hidden;border:1px solid var(--trait);{"grid-column:span 2;" if i < 2 else ""}">
<div style="height:{230 if i < 2 else 150}px;background:{h};color:{fg};padding:22px 24px;display:flex;flex-direction:column;justify-content:flex-end"><p class="t" style="font-size:{34 if i < 2 else 24}px;font-weight:800">{n}</p></div>
<div style="padding:16px 24px;font-size:14px;line-height:1.7;color:var(--gris)"><b style="color:var(--nuit)">{h}</b> &nbsp; RVB {rvb(h)}<br>CMJN {cmjn(h)} &nbsp; · &nbsp; {p}<br><span style="color:var(--nuit);font-weight:600">{u}</span></div></div>'''
    for i, (n, h, p, u, fg) in enumerate(COUL))
PL.append(f"""<div class="pl" style="padding-top:60px"><p class="sur">Couleurs</p><h2 style="font-size:48px">Deux couleurs pour le logo, quatre pour l'univers</h2>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:28px">{tuiles}</div>
<p style="position:absolute;left:90px;bottom:70px;font-size:13px;color:#94A3B8">Les équivalences CMJN et Pantone sont indicatives : à valider sur nuancier et avec l'imprimeur avant tout tirage.</p>{pied(4)}</div>""")

# 5 — Typographie
PL.append(f"""<div class="pl"><p class="sur">Typographie</p><h2>Deux familles, une voix</h2>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:50px;margin-top:44px">
 <div style="border:1px solid var(--trait);border-radius:24px;padding:36px">
  <p style="font-size:14px;font-weight:700;color:var(--gris)">TITRES · Bricolage Grotesque, gras 700–800</p>
  <p class="t" style="font-size:150px;font-weight:800;margin-top:20px">Aa</p>
  <p class="t" style="font-size:44px;font-weight:800;margin-top:20px">Votre pressing, enfin <span style="color:var(--bleu)">simple.</span></p>
  <p class="t" style="font-size:22px;font-weight:600;margin-top:22px;color:var(--gris);letter-spacing:0">ABCDEFGHIJKLMNOPQRSTUVWXYZ<br>abcdefghijklmnopqrstuvwxyz 0123456789 € F</p>
 </div>
 <div style="border:1px solid var(--trait);border-radius:24px;padding:36px">
  <p style="font-size:14px;font-weight:700;color:var(--gris)">TEXTE · Plus Jakarta Sans, 400–700</p>
  <p style="font-size:150px;font-weight:600;margin-top:20px;line-height:1;letter-spacing:-.03em">Aa</p>
  <p style="font-size:22px;line-height:1.6;margin-top:20px;color:#334155">Commandes, paiements et clients prévenus au même endroit, sur téléphone, tablette ou ordinateur.</p>
  <p style="font-size:15px;line-height:1.6;margin-top:22px;color:var(--gris)">Chiffres tabulaires pour les montants : <b style="font-variant-numeric:tabular-nums;color:var(--nuit)">12 500 F · 3 000 F</b></p>
 </div></div>
<p style="font-size:15px;color:var(--gris);margin-top:26px;max-width:1300px;line-height:1.6">Le nom « PressLink » du logo est un <b>dessin sur mesure</b> (trait rond monoligne), pas une police : on utilise toujours les fichiers du logo, jamais le nom retapé au clavier. Les deux familles sont libres de droits (licence SIL Open Font) et disponibles sur Google Fonts.</p>{pied(5)}</div>""")

# 6 — À ne pas faire
sym = lambda st="", f="presslink-symbole-couleur.svg": svg(f, f"width:150px;height:150px;{st}")
P2 = shift(P, 0, 0); L2 = shift(L, 30, -20)
recompose = f'<svg viewBox="0 0 256 256" style="width:150px;height:150px"><path fill="#0F172A" d="{P}"/><path fill="#2563EB" d="{L2}"/></svg>'
couleurs = f'<svg viewBox="0 0 256 256" style="width:150px;height:150px"><path fill="#DC2626" d="{P}"/><path fill="#F59E0B" d="{L}"/></svg>'
contour = f'<svg viewBox="0 0 256 256" style="width:150px;height:150px"><path fill="none" stroke="#0F172A" stroke-width="5" d="{P}"/><path fill="none" stroke="#2563EB" stroke-width="5" d="{L}"/></svg>'
MAUVAIS = [
    (sym("transform:scale(1.7,.8)"), "Déformer"),
    (sym("transform:rotate(-14deg)"), "Pencher"),
    (couleurs, "Changer les couleurs"),
    (recompose, "Déplacer l'accroche"),
    (sym("filter:drop-shadow(10px 12px 6px rgba(0,0,0,.45))"), "Ajouter des effets"),
    (contour, "Utiliser en contour"),
    (f'<div style="width:100%;height:100%;display:grid;place-items:center;background:url({R}/crea/reseaux-story.png) center/cover">{sym("", "presslink-symbole-noir.svg")}</div>', "Poser sur un fond chargé"),
    (f'<p style="font:600 64px/1 Georgia,serif;color:var(--nuit)">PressLink</p>', "Retaper le nom"),
]
cell = lambda c, t: f'<div><div class="case" style="height:250px;overflow:hidden">{c}<span style="position:absolute;right:16px;top:14px;width:30px;height:30px;border-radius:50%;background:#DC2626;color:#fff;display:grid;place-items:center;font-weight:800">✕</span></div><p style="font-size:16px;font-weight:700;margin-top:12px">{t}</p></div>'
PL.append(f"""<div class="pl"><p class="sur" style="color:#DC2626">À éviter</p><h2>Ce qu'on ne fait pas au logo</h2>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-top:40px">{''.join(cell(c, t) for c, t in MAUVAIS)}</div>{pied(6)}</div>""")

# 7 — L'univers en application
img = lambda f, st: f'<img src="{R}/{f}" style="display:block;border-radius:14px;box-shadow:0 30px 60px -30px rgba(15,23,42,.5);{st}">'
PL.append(f"""<div class="pl" style="background:var(--fond)"><p class="sur">Univers</p><h2>Le logo au travail</h2>
<div style="position:absolute;left:90px;right:90px;top:250px;bottom:80px;display:grid;grid-template-columns:1.25fr 1fr .8fr .55fr;gap:22px;align-items:start">
 <div style="display:grid;gap:22px">{img('crea/carte-visite-recto.png','width:100%')}{img('crea/image-partage-og.png','width:100%')}</div>
 <div style="display:grid;gap:22px">{img('crea/carte-visite-verso.png','width:100%')}{img('crea/reseaux-post-carre.png','width:100%')}</div>
 <div style="display:grid;gap:22px">{img('crea/flyer-a5.png','width:100%')}</div>
 <div style="display:grid;gap:22px">{img('crea/etiquette-vetement.png','width:100%')}{img('icones/app/icone-app-1024.png','width:100%;border-radius:22%')}</div>
</div>{pied(7)}</div>""")

if __name__ == "__main__":
    base = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
            "--virtual-time-budget=8000", "--allow-file-access-from-files"]
    noms = ["01-logo", "02-versions", "03-protection-tailles", "04-couleurs", "05-typographie", "06-a-eviter", "07-univers"]
    for nom, corps in zip(noms, PL):
        page = os.path.join(TMP, f"charte-{nom}.html")
        open(page, "w").write(HEAD + corps + "</body></html>")
        subprocess.run(base + ["--window-size=1600,1000", f"--screenshot={OUT}/charte-{nom}.png", "file://" + page],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=90)
        print("✓", nom)
    tout = os.path.join(TMP, "charte-complete.html")
    open(tout, "w").write(HEAD + "".join(PL) + "</body></html>")
    subprocess.run(base + ["--no-pdf-header-footer", f"--print-to-pdf={OUT}/PressLink-planches-synthese.pdf", "file://" + tout],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=120)
    print("✓ PDF")
