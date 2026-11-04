"""Charte graphique complète de PressLink : document A4 (PDF) et aperçus PNG des pages."""
import os, re, subprocess
from logo import *
import motion

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "charte")
TMP = os.path.join(HERE, "_html", "doc"); os.makedirs(TMP, exist_ok=True)
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
R = "../../.."  # racine docs/identite-presslink vue depuis _build/_html/doc
TEL, MAIL, SITE = "07 18 00 22 93", "contact@presslink.org", "presslink.org"

def svg(name, style="", dossier="logo"):
    s = open(os.path.join(HERE, "..", dossier, name)).read()
    s = re.sub(r"<title[^>]*>.*?</title>", "", s)
    return s.replace("<svg ", f'<svg style="{style}" ', 1)

def img(chemin, style=""):
    return f'<img src="{R}/{chemin}" style="display:block;{style}">'

def lum(h):
    c = [int(h[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    c = [x / 12.92 if x <= 0.03928 else ((x + 0.055) / 1.055) ** 2.4 for x in c]
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]

def contraste(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)

def cmjn(h):
    r, g, b = (int(h[i:i + 2], 16) / 255 for i in (1, 3, 5))
    k = 1 - max(r, g, b)
    c, m, y = ((1 - x - k) / (1 - k) for x in (r, g, b))
    return " ".join(str(round(v * 100)) for v in (c, m, y, k))

def rvb(h): return " ".join(str(int(h[i:i + 2], 16)) for i in (1, 3, 5))

CSS = """
:root{--nuit:#0F172A;--encre:#0B1530;--bleu:#2563EB;--clair:#60A5FA;--ciel:#EFF4FE;--vert:#16A34A;--or:#FBBF24;--fond:#F5F7FC;--gris:#475569;--doux:#64748B;--trait:#E2E8F0;--rouge:#DC2626}
*{box-sizing:border-box;margin:0;padding:0}
html{font-size:3.55mm}
body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--nuit);line-height:1.55;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pg{position:relative;width:210mm;height:297mm;overflow:hidden;background:#fff;padding:20mm 18mm 22mm;page-break-after:always;break-after:page}
.pg.sombre{background:var(--encre);color:#fff}
.t,h1,h2,h3{font-family:'Bricolage Grotesque',sans-serif;letter-spacing:-.03em;line-height:1.05}
.sur{font-size:.8rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--bleu)}
h1{font-size:9mm;font-weight:800;margin-top:3mm}
h2{font-size:5.2mm;font-weight:800;margin-top:7mm}
h3{font-size:4.2mm;font-weight:700}
p{margin-top:2.6mm}
.chapo{font-size:1.25rem;line-height:1.5;color:var(--gris);margin-top:4mm}
.petit{font-size:.82rem;color:var(--doux);line-height:1.5}
.pied{position:absolute;left:18mm;right:18mm;bottom:10mm;display:flex;justify-content:space-between;font-size:.72rem;color:#94A3B8;font-weight:600}
.sombre .pied{color:#64748B}
.case{border:.3mm solid var(--trait);border-radius:4mm;display:grid;place-items:center;position:relative;overflow:hidden}
.leg{font-size:.78rem;font-weight:700;color:var(--gris);margin-top:2mm}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:6mm}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm}
ul.l{list-style:none;margin-top:3mm}
ul.l li{position:relative;padding-left:5mm;margin-top:1.8mm}
ul.l li::before{content:"";position:absolute;left:0;top:1.9mm;width:2mm;height:2mm;border-radius:1mm;background:var(--bleu)}
ul.non li::before{background:var(--rouge)}
table.tb{width:100%;border-collapse:collapse;margin-top:4mm;font-size:.9rem}
table.tb th{text-align:left;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--doux);padding:2mm 2mm 2mm 0;border-bottom:.4mm solid var(--nuit)}
table.tb td{padding:2.3mm 2mm 2.3mm 0;border-bottom:.3mm solid var(--trait);vertical-align:top}
.ok,.ko{display:inline-grid;place-items:center;width:5mm;height:5mm;border-radius:50%;color:#fff;font-weight:800;font-size:.7rem}
.ok{background:var(--vert)}.ko{background:var(--rouge)}
.badge{position:absolute;right:3mm;top:3mm}
.encart{background:var(--fond);border-radius:4mm;padding:5mm 6mm;margin-top:5mm}
.num{font-family:'Bricolage Grotesque';font-weight:800;color:var(--bleu)}
@page{size:210mm 297mm;margin:0}
"""
HEAD = ('<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>PressLink · Charte graphique</title>'
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800'
        '&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
        f'<style>{CSS}</style></head><body>')

PAGES = []      # (section, html, sombre)
SECTIONS = []   # (numéro, titre, index de la première page)

def section(num, titre, chapo):
    SECTIONS.append((num, titre, len(PAGES)))
    PAGES.append((titre, f"""
<div style="position:absolute;width:190mm;height:190mm;right:-70mm;top:-60mm;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.55),transparent 65%)"></div>
<div style="position:absolute;left:18mm;right:18mm;bottom:40mm">
 <p class="t" style="font-size:40mm;font-weight:800;color:var(--clair);line-height:1">{num}</p>
 <h1 style="font-size:14mm;margin-top:6mm">{titre}</h1>
 <p style="font-size:1.3rem;line-height:1.5;color:#CBD5E1;margin-top:6mm;max-width:140mm">{chapo}</p>
</div>""", True))

def page(titre_section, corps):
    PAGES.append((titre_section, corps, False))

P, L = symbole()
Ps, Ls = symbole_petit()

# ================================================================ couverture
PAGES.append(("", f"""
<div style="position:absolute;width:240mm;height:240mm;right:-90mm;top:-100mm;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.6),transparent 65%)"></div>
<div style="position:absolute;right:-30mm;bottom:-40mm;opacity:.06">{svg('presslink-symbole-blanc.svg','width:200mm')}</div>
<p class="sur" style="color:var(--clair);position:relative">Charte graphique</p>
<div style="position:relative;margin-top:70mm">{svg('presslink-horizontal-negatif.svg','height:30mm;display:block;margin-left:-4mm')}</div>
<h1 style="position:relative;font-size:15mm;margin-top:18mm;max-width:150mm">Charte graphique<br>de la marque</h1>
<p style="position:relative;font-size:1.3rem;color:#CBD5E1;margin-top:6mm;max-width:140mm">Le logo, les couleurs, la typographie, la voix et leurs règles d'usage, du favicon à l'enseigne.</p>
<div style="position:absolute;left:18mm;bottom:22mm;font-size:.9rem;color:#94A3B8;line-height:1.7">Version 1.0 · novembre 2026<br>Document de référence · usage interne et partenaires</div>""", True))

# ================================================================ sommaire (rempli plus bas)
PAGES.append(("Sommaire", "@@SOMMAIRE@@", False))

# ================================================================ avant-propos
page("Avant-propos", f"""
<p class="sur">Avant-propos</p><h1>Pourquoi cette charte</h1>
<p class="chapo">Une marque se reconnaît parce qu'elle se répète à l'identique. Sur un ticket de dépôt, une story WhatsApp ou une enseigne à Cocody, PressLink doit avoir la même tête, la même voix et les mêmes couleurs.</p>
<h2>À qui elle s'adresse</h2>
<p>À toute personne qui produit quelque chose au nom de PressLink : l'équipe, les commerciaux, les graphistes, les imprimeurs, les développeurs du site et de l'application, et les pressings partenaires qui affichent la marque dans leur boutique.</p>
<h2>Comment l'utiliser</h2>
<ul class="l">
 <li><b>Toujours partir des fichiers fournis</b> (dossier <i>identite-presslink</i>). On ne redessine pas le logo, on ne le retape pas, on ne le capture pas en photo d'écran.</li>
 <li><b>Choisir la bonne version</b> selon le support et la taille (chapitre 02).</li>
 <li><b>Respecter les codes couleur</b> exacts : HEX pour l'écran, CMJN pour l'impression (chapitre 03).</li>
 <li><b>Écrire comme PressLink parle</b> : simple, concret, rassurant (chapitre 06).</li>
</ul>
<div class="encart"><h3>En cas de doute</h3><p>Choisir la solution la plus simple : le logo horizontal en couleur sur fond blanc, avec beaucoup d'air autour. Et demander avant d'imprimer : <b>{MAIL}</b>.</p></div>
<h2>Ce que contient le dossier</h2>
<table class="tb"><tr><th>Dossier</th><th>Contenu</th></tr>
<tr><td><b>logo/</b></td><td>Les 20 fichiers vectoriels (SVG) du logo : 5 versions × 4 couleurs.</td></tr>
<tr><td><b>icones/</b></td><td>Favicon, icônes d'application, icônes de notification, logo pour e-mail.</td></tr>
<tr><td><b>crea/</b></td><td>Modèles prêts : carte de visite, ticket, étiquette, autocollant, flyer, réseaux sociaux.</td></tr>
<tr><td><b>motion/</b></td><td>L'animation du logo (MP4 et GIF).</td></tr>
<tr><td><b>charte/</b></td><td>Ce document et ses planches de synthèse.</td></tr></table>""")

# ================================================================ 01 marque
section("01", "La marque", "Ce que PressLink fait, pour qui, et la façon dont la marque se comporte.")
page("La marque", f"""
<p class="sur">01 · La marque</p><h1>PressLink en bref</h1>
<p class="chapo">PressLink est le logiciel de gestion des pressings en Côte d'Ivoire. Il réunit les commandes, les paiements et les clients au même endroit, sur téléphone, tablette ou ordinateur.</p>
<div class="g2" style="margin-top:8mm">
 <div class="encart" style="margin:0"><p class="sur">Pour les pressings</p><p style="font-size:1.05rem">Enregistrer un dépôt en quelques secondes, suivre chaque vêtement, encaisser en espèces, Wave ou Orange Money, et savoir ce qui est prêt, payé ou en retard.</p></div>
 <div class="encart" style="margin:0"><p class="sur">Pour leurs clients</p><p style="font-size:1.05rem">Être prévenu dès que c'est prêt, connaître le montant à régler et suivre sa commande sans appeler ni se déplacer pour rien.</p></div>
</div>
<h2>Mission</h2>
<p>Rendre la gestion d'un pressing aussi simple qu'envoyer un message, pour que le gérant passe son temps sur le linge et ses clients, pas sur les cahiers.</p>
<h2>Vision</h2>
<p>Devenir le lien naturel entre chaque pressing d'Afrique de l'Ouest et ses clients : quand on dépose un vêtement, on sait qu'il est suivi.</p>
<h2>Promesse</h2>
<p class="t" style="font-size:8mm;font-weight:800;margin-top:3mm">Votre pressing, enfin <span style="color:var(--bleu)">simple.</span></p>
<h2>Les deux phrases de la marque</h2>
<table class="tb"><tr><th>Phrase</th><th>Rôle</th><th>Où l'utiliser</th></tr>
<tr><td><b>« Votre pressing, enfin simple. »</b></td><td>Signature commerciale, s'adresse au gérant.</td><td>Publicités, flyers, bannières, site.</td></tr>
<tr><td><b>« Le lien entre votre pressing et vos clients »</b></td><td>Descripteur : dit ce qu'est PressLink.</td><td>Carte de visite, signature e-mail, présentations.</td></tr></table>
<p class="petit" style="margin-top:4mm">Ces phrases ne font pas partie du logo : elles s'écrivent en texte, en Plus Jakarta Sans, jamais collées au symbole.</p>""")

VALEURS = [("Simple", "On enlève avant d'ajouter. Un écran, une action, des mots de tous les jours."),
           ("Fiable", "Les chiffres sont justes, les données sont protégées, l'application marche même quand internet coupe."),
           ("Proche", "On parle comme au comptoir. On connaît Abidjan, Wave, les coupures et les jours de fête."),
           ("Efficace", "Chaque seconde gagnée au dépôt compte. Le client est prévenu sans un appel.")]
page("La marque", f"""
<p class="sur">01 · La marque</p><h1>Personnalité et valeurs</h1>
<p class="chapo">Si PressLink était une personne, ce serait le gérant le mieux organisé du quartier : calme, précis, toujours souriant, jamais compliqué.</p>
<div class="g2" style="margin-top:7mm;gap:5mm">{''.join(f'<div style="border:.3mm solid var(--trait);border-radius:4mm;padding:5mm 6mm"><p class="num" style="font-size:1rem">0{i+1}</p><h3 style="font-size:6mm;margin-top:1mm">{v}</h3><p style="color:var(--gris)">{d}</p></div>' for i,(v,d) in enumerate(VALEURS))}</div>
<h2>Nous sommes… nous ne sommes pas…</h2>
<table class="tb"><tr><th>PressLink est</th><th>PressLink n'est pas</th></tr>
{''.join(f'<tr><td><span class="ok">✓</span>&nbsp; {a}</td><td><span class="ko">✕</span>&nbsp; {b}</td></tr>' for a,b in [("Clair et direct","Technique ou jargonneux"),("Chaleureux et respectueux","Familier ou moqueur"),("Moderne et soigné","Clinquant ou « startup » à tout prix"),("Rassurant","Alarmiste ou pressant"),("Ivoirien et ouvert","Folklorique ou cliché")])}</table>""")

# ================================================================ 02 logo
section("02", "Le logo", "L'idée, la construction, les versions et toutes les règles pour bien l'utiliser.")
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>L'idée : l'accroche</h1>
<div style="display:grid;grid-template-columns:78mm 1fr;gap:9mm;margin-top:8mm;align-items:center">
 <div class="case" style="height:78mm;background:var(--fond);border:0">{svg('presslink-symbole-couleur.svg','width:58mm')}</div>
 <div><p class="chapo" style="margin:0">Le symbole assemble un <b style="color:var(--nuit)">P</b> (le pressing) et un <b style="color:var(--bleu)">L</b> (le lien, le client).</p>
 <p>Le L remonte sous la barre du P et sa pointe vient se <b>clipser dans la boucle</b>, comme un cintre qu'on accroche ou un ticket qu'on agrafe au vêtement.</p>
 <p>Les deux lettres ne se touchent pas, mais elles sont <b>indissociables</b> : sans le P, le L n'a plus rien à tenir ; sans le L, le P est vide. C'est exactement la relation entre un pressing et ses clients.</p></div>
</div>
<h2>Pourquoi ce dessin</h2>
<ul class="l">
 <li><b>Une seule idée</b>, lisible en un coup d'œil, qui s'explique en une phrase.</li>
 <li><b>Des formes rondes</b> et un trait régulier : un dessin amical, facile à reproduire en broderie, en tampon ou en sérigraphie.</li>
 <li><b>Deux couleurs</b> qui portent le sens : le nuit pour le pressing, le bleu pour le lien.</li>
 <li><b>Lisible à 16 pixels</b> grâce à une coupe dédiée aux petites tailles.</li>
 <li><b>Loin des clichés</b> du secteur (cintres, bulles, gouttes, machines à laver).</li>
</ul>
<h2>Les quatre éléments</h2>
<div class="g4" style="margin-top:4mm">{''.join(f'<div><div class="case" style="height:30mm">{c}</div><p class="leg">{t}</p></div>' for c,t in [
  (f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="#0F172A" d="{P}"/><path fill="#E2E8F0" d="{L}"/></svg>', "Le P · le pressing"),
  (f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="#E2E8F0" d="{P}"/><path fill="#2563EB" d="{L}"/></svg>', "Le L · le lien"),
  (f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="#E2E8F0" d="{P}"/><path fill="#E2E8F0" d="{L}"/><path fill="#2563EB" d="{L.split(" M")[0]}"/></svg>', "L'accroche"),
  (svg('presslink-nom-couleur.svg','width:34mm'), "Le nom dessiné")])}</div>""")

# construction
grille = "".join(f'<line x1="{i}" y1="0" x2="{i}" y2="256" stroke="#DBE3F0" stroke-width=".5"/><line x1="0" y1="{i}" x2="256" y2="{i}" stroke="#DBE3F0" stroke-width=".5"/>' for i in range(0, 257, 8))
cons = f"""<svg viewBox="-12 -12 280 280" style="width:100%">
<rect x="0" y="0" width="256" height="256" fill="#fff"/>{grille}
<path fill="#0F172A" fill-opacity=".12" d="{P}"/><path fill="#2563EB" fill-opacity=".18" d="{L}"/>
<path fill="none" stroke="#0F172A" stroke-width="1" d="{P}"/><path fill="none" stroke="#2563EB" stroke-width="1" d="{L}"/>
<circle cx="120" cy="94" r="48" fill="none" stroke="#DC2626" stroke-width="1" stroke-dasharray="3 3"/>
<line x1="120" y1="20" x2="120" y2="240" stroke="#DC2626" stroke-width="1" stroke-dasharray="3 3"/>
<line x1="56" y1="20" x2="56" y2="240" stroke="#DC2626" stroke-width=".8" stroke-dasharray="2 3"/>
<circle cx="120" cy="94" r="2.5" fill="#DC2626"/>
<g font-family="Plus Jakarta Sans" font-weight="700" font-size="8" fill="#DC2626">
<text x="124" y="12">axe commun</text><text x="170" y="60">r = 48</text><text x="10" y="252">trait = 28 (x)</text>
<text x="140" y="125">12</text><text x="140" y="166">12</text></g>
<g stroke="#DC2626" stroke-width="1"><line x1="137" y1="116" x2="137" y2="128"/><line x1="137" y1="156" x2="137" y2="168"/></g>
</svg>"""
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Construction</h1>
<p class="chapo">Le symbole est dessiné sur une grille de 256 unités, avec un seul trait de 28 unités et des terminaisons rondes.</p>
<div style="display:grid;grid-template-columns:100mm 1fr;gap:8mm;margin-top:6mm">
 <div class="case" style="padding:3mm">{cons}</div>
 <div><table class="tb" style="margin-top:0"><tr><th>Élément</th><th>Mesure</th></tr>
 <tr><td>Trait (unité x)</td><td>28</td></tr><tr><td>Rayon des terminaisons</td><td>14 (x / 2)</td></tr>
 <tr><td>Boucle du P</td><td>rayon médian 48, centre (120, 94)</td></tr>
 <tr><td>Accroche</td><td>capsule de 28 × 48</td></tr>
 <tr><td>Jour accroche / barre</td><td>12</td></tr><tr><td>Jour barre / L</td><td>12</td></tr>
 <tr><td>Pied du L</td><td>jusqu'à x = 214</td></tr></table></div>
</div>
<h2>Trois règles de dessin</h2>
<ul class="l">
 <li><b>Un axe commun.</b> Le centre de la boucle, l'accroche et la hampe du L sont sur la même verticale : c'est ce qui donne l'impression que le L « tient » dans le P.</li>
 <li><b>Des jours égaux.</b> Les deux espaces autour de la barre du P mesurent 12 unités : le L passe <i>sous</i> la barre sans la toucher.</li>
 <li><b>Une coupe petite.</b> Sous 24 px, le trait passe à 36 unités et l'accroche devient une pastille : les jours restent ouverts à l'écran.</li>
</ul>
<div class="g2" style="margin-top:5mm"><div><div class="case" style="height:34mm">{svg('presslink-symbole-couleur.svg','width:26mm')}</div><p class="leg">Symbole standard · dès 24 px / 6 mm</p></div>
<div><div class="case" style="height:34mm">{svg('presslink-symbole-petit-couleur.svg','width:26mm')}</div><p class="leg">Coupe petite · de 16 à 23 px, favicon</p></div></div>""")

page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Le nom</h1>
<p class="chapo">« PressLink » est dessiné sur mesure, d'un trait rond et régulier qui prolonge celui du symbole. Ce n'est pas une police : on utilise toujours les fichiers fournis.</p>
<div class="case" style="height:46mm;margin-top:7mm;background:var(--fond);border:0">{svg('presslink-nom-couleur.svg','width:150mm')}</div>
<div class="g2" style="margin-top:6mm">
 <div><h3>Deux couleurs, deux mots</h3><p><b>Press</b> en nuit, <b>Link</b> en bleu : le nom répète la logique du symbole (le pressing et le lien).</p></div>
 <div><h3>Une grille simple</h3><p>Capitales de 140 unités, hauteur d'x de 90, trait de 24, angles du k à 45°. Les espaces entre lettres sont réglés à l'œil, lettre par lettre.</p></div>
</div>
<h2>Écrire le nom dans un texte</h2>
<table class="tb"><tr><th>On écrit</th><th>On n'écrit pas</th></tr>
<tr><td><span class="ok">✓</span>&nbsp; <b>PressLink</b> : un seul mot, P et L en capitales</td><td><span class="ko">✕</span>&nbsp; Presslink · Press Link · Press-Link · PRESSLINK · presslink</td></tr>
<tr><td><span class="ok">✓</span>&nbsp; l'application PressLink, chez PressLink</td><td><span class="ko">✕</span>&nbsp; le PressLink, les PressLinks</td></tr>
<tr><td><span class="ok">✓</span>&nbsp; presslink.org (adresse web, en minuscules)</td><td><span class="ko">✕</span>&nbsp; PressLink.org, www.PressLink.org</td></tr></table>
<p class="petit" style="margin-top:4mm">Dans un texte courant, le nom s'écrit dans la police du texte, sans couleur particulière. Le nom en deux couleurs est réservé au logo.</p>""")

VERS = [("presslink-horizontal-couleur.svg", "Horizontal · version principale", "C'est le logo par défaut : site, documents, signature, publicités. À utiliser dès que la place le permet.", "height:16mm"),
        ("presslink-empile-couleur.svg", "Empilé", "Pour les formats carrés ou verticaux : affiches, autocollants, écrans de démarrage, couvertures.", "height:30mm"),
        ("presslink-symbole-couleur.svg", "Symbole seul", "Quand la marque est déjà nommée ou que la place manque : icône d'application, favicon, avatar, broderie, tampon.", "height:28mm"),
        ("presslink-nom-couleur.svg", "Nom seul", "Usage rare : quand le symbole est déjà présent à proximité (par exemple sur une page où l'icône est affichée).", "height:11mm")]
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Les versions</h1>
<p class="chapo">Quatre assemblages, toujours à partir des mêmes éléments. Leurs proportions sont fixées : on ne recompose pas le logo soi-même.</p>
<div style="display:grid;gap:5mm;margin-top:7mm">{''.join(f'<div style="display:grid;grid-template-columns:86mm 1fr;gap:7mm;align-items:center"><div class="case" style="height:42mm">{svg(f, st)}</div><div><h3>{t}</h3><p style="color:var(--gris)">{d}</p></div></div>' for f,t,d,st in VERS)}</div>""")

FONDS = [("presslink-horizontal-couleur.svg", "#FFFFFF", "Blanc", True), ("presslink-horizontal-couleur.svg", "#F5F7FC", "Fond clair", True),
         ("presslink-horizontal-negatif.svg", "#0B1530", "Encre / nuit", True), ("presslink-horizontal-blanc.svg", "#2563EB", "Bleu PressLink", True),
         ("presslink-horizontal-noir.svg", "#FFFFFF", "Noir · fax, tampon, gravure", True), ("presslink-horizontal-couleur.svg", "#2563EB", "Couleur sur bleu", False),
         ("presslink-horizontal-negatif.svg", "#60A5FA", "Négatif sur bleu clair", False), ("presslink-horizontal-couleur.svg", "#16A34A", "Sur le vert action", False)]
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Couleurs du logo et fonds</h1>
<p class="chapo">Chaque version existe en quatre couleurs. Le choix dépend uniquement du fond.</p>
<table class="tb"><tr><th>Version</th><th>Couleurs</th><th>Sur quel fond</th></tr>
<tr><td><b>Couleur</b></td><td>P nuit · L bleu</td><td>Blanc, fond clair, photos très claires</td></tr>
<tr><td><b>Négatif</b></td><td>P blanc · L bleu clair</td><td>Encre, nuit, photos sombres</td></tr>
<tr><td><b>Blanc</b></td><td>Tout blanc</td><td>Bleu PressLink, couleurs vives, photos chargées</td></tr>
<tr><td><b>Noir</b></td><td>Tout noir</td><td>Impression une couleur, ticket thermique, tampon, gravure</td></tr></table>
<div class="g4" style="margin-top:7mm">{''.join(f'<div><div class="case" style="height:26mm;background:{bg};{"border-color:transparent" if bg not in ("#FFFFFF","#F5F7FC") else ""}">{svg(f,"width:36mm")}<span class="badge {"ok" if ok else "ko"}">{"✓" if ok else "✕"}</span></div><p class="leg">{t}</p></div>' for f,bg,t,ok in FONDS)}</div>
<div class="encart"><h3>Sur une photo</h3><p>Le logo se pose sur une zone calme de l'image (ciel, mur, tissu uni). Si le fond est chargé, on utilise la version blanche sur un bandeau ou un cartouche nuit, jamais le logo seul sur un fond qui le brouille.</p></div>""")

pad = 90 * 28 / 256
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Zone de protection</h1>
<p class="chapo">Autour du logo, une zone vide garantit sa lisibilité. Sa largeur est <b style="color:var(--nuit)">x</b>, l'épaisseur du trait du P.</p>
<div class="case" style="height:70mm;margin-top:7mm;background:var(--fond);border:0">
 <div style="position:relative;padding:{pad*1.0:.2f}mm;outline:.4mm dashed #93C5FD;background:repeating-linear-gradient(45deg,rgba(37,99,235,.07) 0 2mm,transparent 2mm 4mm)">
  <div style="background:var(--fond)">{svg('presslink-horizontal-couleur.svg','height:28mm;display:block')}</div>
  <span class="t" style="position:absolute;left:0;top:50%;width:{pad:.2f}mm;margin-top:-2.5mm;text-align:center;font-weight:800;color:var(--bleu)">x</span>
  <span class="t" style="position:absolute;left:50%;top:0;height:{pad:.2f}mm;display:grid;place-items:center;font-weight:800;color:var(--bleu)">x</span>
 </div></div>
<ul class="l" style="margin-top:6mm">
 <li>La zone de protection vaut <b>x sur les quatre côtés</b>, mesurée depuis le bord du dessin (pas du fichier).</li>
 <li>Aucun texte, image, bord de page ou autre logo ne doit y entrer.</li>
 <li>Les fichiers SVG fournis incluent déjà une marge proche de cette zone.</li>
 <li>Quand c'est possible, on laisse <b>beaucoup plus</b> que le minimum.</li>
</ul>
<h2>Placement sur un document</h2>
<div class="g3" style="margin-top:4mm">
 {''.join(f'<div><div class="case" style="height:42mm;align-items:{al};justify-items:{ju};padding:5mm">{svg("presslink-horizontal-couleur.svg","height:5mm")}</div><p class="leg">{t}</p></div>' for al,ju,t in [("start","start","En haut à gauche · par défaut"),("center","center","Centré · couvertures, affiches"),("end","start","En bas à gauche · pied de page")])}
</div>
<p class="petit" style="margin-top:3mm">Marge conseillée entre le logo et le bord : au moins 2 x, et la même marge que le texte de la page.</p>""")

tail = lambda px, f="presslink-symbole-couleur.svg": f'<div style="text-align:center"><div style="height:18mm;display:grid;place-items:end center">{svg(f, f"width:{px}px;height:{px}px")}</div><p class="leg">{px} px</p></div>'
page("Le logo", f"""
<p class="sur">02 · Le logo</p><h1>Tailles minimales</h1>
<p class="chapo">En dessous de ces tailles, les jours se referment et le logo ne se lit plus.</p>
<table class="tb"><tr><th>Version</th><th>Écran</th><th>Impression</th></tr>
<tr><td><b>Horizontal</b></td><td>28 px de haut</td><td>10 mm de haut</td></tr>
<tr><td><b>Empilé</b></td><td>64 px de haut</td><td>18 mm de haut</td></tr>
<tr><td><b>Symbole</b></td><td>24 px</td><td>6 mm</td></tr>
<tr><td><b>Symbole · coupe petite</b></td><td>16 px</td><td>4 mm</td></tr>
<tr><td><b>Broderie</b></td><td colspan="2">Symbole de 15 mm minimum, nom de 40 mm de large minimum</td></tr></table>
<h2>Le symbole de 64 à 16 px</h2>
<div style="display:flex;gap:8mm;align-items:flex-end;margin-top:5mm">{tail(64)}{tail(48)}{tail(32)}{tail(24)}{tail(16,'presslink-symbole-petit-couleur.svg')}</div>
<h2>Le logo horizontal en petit</h2>
<div style="display:grid;gap:4mm;margin-top:4mm">{''.join(f'<div style="display:flex;align-items:center;gap:6mm">{svg("presslink-horizontal-couleur.svg",f"height:{h}px")}<span class="leg" style="margin:0">{h} px</span></div>' for h in (64, 44, 28))}</div>
<div class="encart"><h3>Bon réflexe</h3><p>À très petite taille, préférer le symbole seul (coupe petite) au logo horizontal : c'est pour cela que le favicon et la notification n'affichent que le symbole.</p></div>""")

pr = lambda d1, d2: f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="#0F172A" d="{d1}"/><path fill="#2563EB" d="{d2}"/></svg>'
sy = lambda st="", f="presslink-symbole-couleur.svg": svg(f, f"width:24mm;height:24mm;{st}")
MAUV = [(sy("transform:scale(1.5,.8)"), "Déformer ou étirer"), (sy("transform:rotate(-14deg)"), "Pencher ou tourner"),
        (f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="#DC2626" d="{P}"/><path fill="#F59E0B" d="{L}"/></svg>', "Changer les couleurs"),
        (pr(P, shift(L, 30, -20)), "Déplacer l'accroche ou le L"),
        (sy("filter:drop-shadow(2mm 2.4mm 1.2mm rgba(0,0,0,.45))"), "Ajouter ombre, relief, dégradé"),
        (f'<svg viewBox="0 0 256 256" style="width:24mm"><path fill="none" stroke="#0F172A" stroke-width="5" d="{P}"/><path fill="none" stroke="#2563EB" stroke-width="5" d="{L}"/></svg>', "Utiliser en contour"),
        (f'<div style="width:100%;height:100%;display:grid;place-items:center;background:url({R}/crea/reseaux-story.png) center/cover">{sy("", "presslink-symbole-noir.svg")}</div>', "Poser sur un fond chargé"),
        ('<p style="font:600 7mm/1 Georgia,serif">PressLink</p>', "Retaper le nom dans une police"),
        (f'<div style="display:flex;align-items:center;gap:2mm">{svg("presslink-symbole-couleur.svg","width:12mm")}<span class="t" style="font-size:6mm;font-weight:800">Pressing Élégance</span></div>', "Remplacer le nom"),
        (f'<div style="display:grid;place-items:center">{svg("presslink-horizontal-couleur.svg","height:7mm")}<span style="font-size:2.6mm;font-weight:700;margin-top:-.5mm">Le lien entre votre pressing…</span></div>', "Coller une phrase au logo"),
        (f'<div style="opacity:.35">{svg("presslink-horizontal-couleur.svg","height:7mm")}</div>', "Rendre transparent"),
        (f'<div style="display:grid;place-items:center;width:100%;height:100%;background:#2563EB">{svg("presslink-horizontal-couleur.svg","height:7mm")}</div>', "Couleur sur fond bleu")]
page("Le logo", f"""
<p class="sur" style="color:var(--rouge)">02 · Le logo</p><h1>Ce qu'on ne fait pas</h1>
<p class="chapo">Le logo ne se modifie jamais. Voici les erreurs les plus fréquentes.</p>
<div class="g4" style="margin-top:7mm;gap:4mm 4mm">{''.join(f'<div><div class="case" style="height:32mm">{c}<span class="badge ko">✕</span></div><p class="leg">{t}</p></div>' for c,t in MAUV)}</div>
<p class="petit" style="margin-top:6mm">Un pressing partenaire qui veut afficher son propre nom le fait <b>à côté</b> du logo PressLink, séparé par la zone de protection, avec la mention « Équipé par PressLink » ou « Suivi PressLink ».</p>""")

# ================================================================ 03 couleurs
section("03", "Les couleurs", "Deux couleurs pour le logo, une palette courte pour tout le reste.")
COUL = [("Bleu PressLink", "#2563EB", "2728 C", "Le L, la tuile d'application, les liens, les éléments actifs."),
        ("Nuit", "#0F172A", "Black 6 C", "Le P et « Press », les titres, le texte principal."),
        ("Bleu clair", "#60A5FA", "284 C", "Le L sur fond sombre, les accents sur fond nuit."),
        ("Encre", "#0B1530", "5395 C", "Les grands fonds sombres : bannières, cartes, sections du site."),
        ("Fond clair", "#F5F7FC", "—", "Les fonds clairs, les encarts, les zones calmes."),
        ("Vert action", "#16A34A", "7740 C", "Uniquement les boutons d'action principaux (« Créer mon espace »)."),
        ("Or", "#FBBF24", "123 C", "Le surlignage d'un mot clé, avec parcimonie."),
        ("Gris texte", "#475569", "7545 C", "Le texte secondaire, les légendes.")]
def fiche(n, h, pan, u, grand=False):
    fg = "var(--nuit)" if lum(h) > 0.4 else "#fff"
    return f'''<div style="border-radius:4mm;overflow:hidden;border:.3mm solid var(--trait)">
<div style="height:{22 if grand else 15}mm;background:{h};color:{fg};padding:3mm 4mm;display:flex;align-items:flex-end"><p class="t" style="font-size:{6 if grand else 4.4}mm;font-weight:800;margin:0">{n}</p></div>
<div style="padding:3mm 4mm;font-size:.78rem;line-height:1.6;color:var(--gris)"><b style="color:var(--nuit);font-size:.9rem">{h}</b><br>RVB {rvb(h)}<br>CMJN {cmjn(h)}<br>Pantone ≈ {pan}<p style="margin-top:1.5mm;color:var(--nuit)">{u}</p></div></div>'''
page("Les couleurs", f"""
<p class="sur">03 · Les couleurs</p><h1>La palette</h1>
<p class="chapo">Le bleu et le nuit sont la marque. Les autres couleurs les accompagnent, sans jamais les remplacer.</p>
<h2>Couleurs principales</h2><div class="g2" style="margin-top:3mm;gap:4mm">{fiche(*COUL[0], True)}{fiche(*COUL[1], True)}</div>
<h2>Couleurs d'accompagnement</h2><div class="g3" style="margin-top:3mm;gap:4mm">{''.join(fiche(*c) for c in COUL[2:5])}</div>
<h2>Couleurs fonctionnelles</h2><div class="g3" style="margin-top:3mm;gap:4mm">{''.join(fiche(*c) for c in COUL[5:])}</div>
<p class="petit" style="margin-top:4mm">Les valeurs CMJN sont calculées et les Pantone sont des équivalents approchés : à valider sur nuancier et avec l'imprimeur (épreuve couleur) avant tout tirage.</p>""")

PAIRES = [("#0F172A", "#FFFFFF", "Nuit sur blanc"), ("#2563EB", "#FFFFFF", "Bleu sur blanc"), ("#FFFFFF", "#2563EB", "Blanc sur bleu"),
          ("#FFFFFF", "#0B1530", "Blanc sur encre"), ("#60A5FA", "#0B1530", "Bleu clair sur encre"), ("#475569", "#FFFFFF", "Gris texte sur blanc"),
          ("#FFFFFF", "#16A34A", "Blanc sur vert action"), ("#FFFFFF", "#15803D", "Blanc sur vert foncé"), ("#60A5FA", "#FFFFFF", "Bleu clair sur blanc")]
def verdict(r):
    if r >= 7: return '<span class="ok">✓</span>&nbsp; AAA · tout texte'
    if r >= 4.5: return '<span class="ok">✓</span>&nbsp; AA · tout texte'
    if r >= 3: return '<span class="ok" style="background:#F59E0B">!</span>&nbsp; Grands textes seulement (≥ 18 pt)'
    return '<span class="ko">✕</span>&nbsp; À éviter pour le texte'
PROP = [("#FFFFFF", 45, "Blanc / fond clair"), ("#0F172A", 25, "Nuit / encre"), ("#2563EB", 20, "Bleu"), ("#60A5FA", 5, ""), ("#16A34A", 3, ""), ("#FBBF24", 2, "")]
page("Les couleurs", f"""
<p class="sur">03 · Les couleurs</p><h1>Proportions et contrastes</h1>
<p class="chapo">Une page PressLink est d'abord claire et aérée. Le bleu guide l'œil, le vert déclenche l'action.</p>
<div style="display:flex;height:13mm;border-radius:3mm;overflow:hidden;margin-top:5mm;border:.3mm solid var(--trait)">{''.join(f'<div style="flex:{w};background:{c};display:flex;align-items:flex-end;padding:2mm 3mm;font-size:.72rem;font-weight:700;color:{"var(--nuit)" if lum(c)>.4 else "#fff"}">{w} %</div>' for c,w,_ in PROP)}</div>
<p class="petit" style="margin-top:2mm">Environ 45 % de blanc ou fond clair, 25 % de nuit, 20 % de bleu, et des touches de bleu clair, vert et or.</p>
<h2>Lisibilité (contraste WCAG 2.1)</h2>
<table class="tb"><tr><th>Exemple</th><th>Association</th><th>Rapport</th><th>Usage</th></tr>
{''.join(f'<tr><td><span style="display:inline-block;padding:1mm 3mm;border-radius:1.5mm;background:{b};color:{a};font-weight:700;border:.3mm solid var(--trait)">Prêt</span></td><td>{n}</td><td><b>{contraste(a,b):.1f} : 1</b></td><td>{verdict(contraste(a,b))}</td></tr>' for a,b,n in PAIRES)}</table>
<div class="encart"><h3>Les règles</h3><ul class="l" style="margin-top:1mm"><li>Le texte courant est en nuit (ou gris texte) sur fond blanc ou fond clair.</li><li>Le bleu clair ne s'utilise que sur fond sombre.</li><li>Le vert est réservé à une seule action par écran ou par page. Texte blanc en gras d’au moins 14 pt sur le vert action ; pour un petit bouton, utiliser le vert foncé <b>#15803D</b>.</li><li>On n'utilise jamais la couleur seule pour transmettre une information : on ajoute toujours un mot ou une icône.</li></ul></div>""")

# ================================================================ 04 typographie
section("04", "La typographie", "Deux familles libres de droits, une hiérarchie claire.")
page("La typographie", f"""
<p class="sur">04 · La typographie</p><h1>Deux familles</h1>
<div class="g2" style="margin-top:7mm">
 <div style="border:.3mm solid var(--trait);border-radius:4mm;padding:6mm">
  <p class="petit"><b>TITRES</b></p><h3 style="font-size:6mm;margin-top:1mm">Bricolage Grotesque</h3>
  <p class="t" style="font-size:28mm;font-weight:800;margin-top:4mm">Aa</p>
  <p class="t" style="font-size:4.6mm;font-weight:600;letter-spacing:-.01em;margin-top:3mm;line-height:1.35;color:var(--gris)">ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789 àéèêç « » F</p>
  <p class="petit" style="margin-top:4mm">Graisses : 600, 700, 800 · interlettrage −3 %<br>Pour les titres, les chiffres clés, les accroches.</p></div>
 <div style="border:.3mm solid var(--trait);border-radius:4mm;padding:6mm">
  <p class="petit"><b>TEXTE</b></p><h3 style="font-size:6mm;margin-top:1mm">Plus Jakarta Sans</h3>
  <p style="font-size:28mm;font-weight:600;margin-top:4mm;line-height:1.05;letter-spacing:-.03em">Aa</p>
  <p style="font-size:4.2mm;margin-top:3mm;line-height:1.45;color:var(--gris)">ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789 àéèêç « » F</p>
  <p class="petit" style="margin-top:4mm">Graisses : 400, 500, 600, 700<br>Pour le texte, les interfaces, les formulaires.</p></div>
</div>
<h2>Pourquoi ces deux familles</h2>
<p><b>Bricolage Grotesque</b> a du caractère : des formes un peu irrégulières, chaleureuses, qui évitent l'effet « logiciel froid ». <b>Plus Jakarta Sans</b> est très lisible sur petit écran, avec des chiffres nets pour les montants.</p>
<p>Les deux sont sous <b>licence SIL Open Font</b> : gratuites, utilisables sur le web, en impression et en application, et disponibles sur Google Fonts.</p>
<h2>Police de secours</h2>
<p>Si ces polices ne sont pas disponibles (Word, PowerPoint sans installation), utiliser <b>Arial</b> : gras pour les titres, normal pour le texte. Le logo, lui, reste toujours une image.</p>
<p class="petit">Le nom « PressLink » du logo est un dessin, pas une police : aucune des deux familles ne le reproduit.</p>""")

ECH = [("Titre 1", "t", 800, "10 mm", "40–56 px", "Votre pressing, enfin simple."), ("Titre 2", "t", 700, "6 mm", "28–32 px", "Vos clients prévenus"),
       ("Titre 3", "t", 700, "4,5 mm", "20 px", "Paiements du jour"), ("Chapô", "", 500, "4,2 mm", "18–20 px", "Commandes, paiements et clients au même endroit."),
       ("Texte", "", 400, "3,5 mm", "15–16 px", "Le client reçoit un message dès que sa commande est prête."), ("Légende", "", 600, "2,8 mm", "12–13 px", "Mis à jour il y a 2 minutes")]
page("La typographie", f"""
<p class="sur">04 · La typographie</p><h1>Hiérarchie et règles</h1>
<table class="tb" style="margin-top:7mm"><tr><th style="width:24mm">Niveau</th><th>Exemple</th><th style="width:20mm">Impression</th><th style="width:20mm">Écran</th></tr>
{''.join(f'<tr><td><b>{n}</b><br><span class="petit">{w}</span></td><td><span class="{c}" style="font-size:{t.replace(",",".").replace(" ","")};font-weight:{w};line-height:1.15;display:block">{ex}</span></td><td>{t}</td><td>{e}</td></tr>' for n,c,w,t,e,ex in ECH)}</table>
<h2>Règles de composition</h2>
<ul class="l">
 <li><b>Aligné à gauche</b>, jamais justifié. Centré seulement pour les titres courts d'affiche.</li>
 <li><b>Interlignage</b> : 1,5 pour le texte, 1,05 à 1,15 pour les titres.</li>
 <li><b>Lignes de 45 à 75 caractères</b> pour un texte confortable.</li>
 <li><b>Un mot en couleur</b> au maximum par titre (en bleu, ou surligné en or).</li>
 <li><b>Pas de capitales</b> pour les phrases ; réservées aux petits surtitres espacés.</li>
</ul>
<h2>Chiffres, montants et dates</h2>
<table class="tb"><tr><th>On écrit</th><th>On évite</th></tr>
<tr><td><span class="ok">✓</span>&nbsp; 12 500 F (espace insécable)</td><td><span class="ko">✕</span>&nbsp; 12500F · 12.500 FCFA · 12 500,00 F</td></tr>
<tr><td><span class="ok">✓</span>&nbsp; mercredi 5 novembre · 05/11</td><td><span class="ko">✕</span>&nbsp; 11/05 · 5 Nov.</td></tr>
<tr><td><span class="ok">✓</span>&nbsp; 07 18 00 22 93</td><td><span class="ko">✕</span>&nbsp; 0718002293 · +225-07-18</td></tr>
<tr><td><span class="ok">✓</span>&nbsp; 9 h 30 · 18 h</td><td><span class="ko">✕</span>&nbsp; 9:30 AM · 18H00</td></tr></table>""")

# ================================================================ 05 éléments graphiques
section("05", "Les éléments graphiques", "Ce qui fait reconnaître PressLink, même sans le logo.")
motif = open(os.path.join(HERE, "..", "crea", "motif-presslink.svg")).read()
page("Les éléments graphiques", f"""
<p class="sur">05 · Les éléments graphiques</p><h1>Motif, halo, surlignage</h1>
<div class="g2" style="margin-top:7mm;align-items:start">
 <div><div class="case" style="height:52mm;background:#fff">{motif.replace('<svg ', '<svg style="width:100%;height:100%;position:absolute;inset:0" preserveAspectRatio="xMidYMid slice" ', 1)}</div>
 <h3 style="margin-top:4mm">Le motif</h3><p style="color:var(--gris)">Le symbole répété en quinconce. Toujours très discret (3 à 8 % d'opacité), en fond de bannière, d'emballage ou de papier. Jamais sous un texte long.</p></div>
 <div><div class="case" style="height:52mm;background:var(--encre);border:0"><div style="position:absolute;width:70mm;height:70mm;right:-20mm;top:-25mm;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.6),transparent 65%)"></div><p class="t" style="position:relative;color:#fff;font-size:7mm;font-weight:800">Fond nuit + halo</p></div>
 <h3 style="margin-top:4mm">Le halo</h3><p style="color:var(--gris)">Une lueur bleue en coin, sur fond encre. Elle donne de la profondeur sans image. Un seul halo par composition, toujours vers un bord.</p></div>
</div>
<div class="g2" style="margin-top:6mm;align-items:start">
 <div><div class="case" style="height:34mm;background:var(--fond);border:0"><p class="t" style="font-size:9mm;font-weight:800">enfin <span style="color:var(--bleu);background:linear-gradient(transparent 78%,rgba(251,191,36,.5) 78%,rgba(251,191,36,.5) 92%,transparent 92%)">simple.</span></p></div>
 <h3 style="margin-top:4mm">Le surlignage</h3><p style="color:var(--gris)">Un trait or sous le mot clé d'un titre, comme un coup de surligneur. Un seul par page.</p></div>
 <div><div class="case" style="height:34mm;background:var(--fond);border:0;gap:2mm;padding:4mm">{''.join(f'<div style="display:flex;align-items:center;gap:3mm;width:62mm;background:{bg};color:{fg};border-radius:3mm;padding:1.6mm 3mm;font-weight:700;font-size:.8rem"><span style="width:5mm;height:5mm;border-radius:50%;display:grid;place-items:center;background:{pb};color:{pf}">{n}</span>{t}</div>' for n,t,bg,fg,pb,pf in [("1","Testez","#fff","var(--nuit)","var(--ciel)","var(--bleu)"),("2","Créez votre pressing","var(--bleu)","#fff","#fff","var(--bleu)")])}</div>
 <h3 style="margin-top:4mm">Les étapes numérotées</h3><p style="color:var(--gris)">Des cartes arrondies avec une pastille numérotée. L'étape recommandée est pleine en bleu.</p></div>
</div>
<h2>Formes et rayons</h2>
<p>Tout est arrondi, comme les terminaisons du logo. Rayons conseillés : <b>12 px</b> pour les boutons et champs, <b>16 à 24 px</b> pour les cartes, <b>999 px</b> (pilule) pour les étiquettes et badges. Ombres douces et rares, jamais de contour épais.</p>""")

page("Les éléments graphiques", f"""
<p class="sur">05 · Les éléments graphiques</p><h1>Icônes et photographie</h1>
<h2>Icônes</h2>
<p>Les icônes PressLink sont <b>dessinées au trait</b>, avec des extrémités rondes et un trait régulier, comme le logo. Elles sont simples, en une ou deux couleurs (nuit et bleu), sur une grille de 24 px avec un trait de 2 px.</p>
<div class="g4" style="margin-top:4mm">{''.join(f'<div><div class="case" style="height:24mm"><svg viewBox="0 0 24 24" style="width:12mm" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{d}</svg></div><p class="leg">{t}</p></div>' for d,t in [
 ('<path stroke="#0F172A" d="M12 4a2 2 0 1 1 2 2c-1 0-2 .7-2 2v1"/><path stroke="#2563EB" d="M12 9 3 15.5a1 1 0 0 0 .6 1.8h16.8a1 1 0 0 0 .6-1.8L12 9"/>', "Article / cintre"),
 ('<rect stroke="#0F172A" x="4" y="3" width="16" height="18" rx="2"/><path stroke="#2563EB" d="M8 8h8M8 12h8M8 16h5"/>', "Ticket"),
 ('<path stroke="#0F172A" d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path stroke="#2563EB" d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>', "Client prévenu"),
 ('<rect stroke="#0F172A" x="2" y="6" width="20" height="13" rx="2"/><path stroke="#2563EB" d="M2 10h20M6 15h4"/>', "Paiement")])}</div>
<p class="petit" style="margin-top:3mm">Exemples indicatifs. Dans l'application, on réutilise la bibliothèque d'icônes existante du produit.</p>
<h2>Photographie</h2>
<div class="g2" style="margin-top:3mm">
 <div><h3 style="color:var(--vert)">On montre</h3><ul class="l"><li>De vrais pressings, de vrais gérants et clients, à Abidjan et en Côte d'Ivoire.</li><li>La lumière naturelle, des tissus, des couleurs de pagne et de boubou.</li><li>Des gestes : remettre un ticket, repasser, consulter son téléphone, sourire au comptoir.</li><li>Le téléphone ou la tablette en situation, l'écran PressLink lisible.</li></ul></div>
 <div><h3 style="color:var(--rouge)">On évite</h3><ul class="l non"><li>Les photos de banque d'images génériques ou d'ailleurs.</li><li>Les filtres forts, le noir et blanc, les couleurs saturées.</li><li>Les mises en scène trop parfaites ou caricaturales.</li><li>Montrer un client sans son accord, ou des données personnelles lisibles à l'écran.</li></ul></div>
</div>
<div class="encart"><h3>Traitement</h3><p>Photos claires et naturelles. Pour poser un texte dessus, on ajoute un dégradé encre (de 0 à 70 %) du côté du texte, plutôt que d'assombrir toute l'image.</p></div>""")

# ================================================================ 06 voix
section("06", "La voix", "PressLink parle comme un bon gérant : clair, poli, utile.")
EX = [("Votre commande est prête ! Vous pouvez passer la récupérer.", "Le statut de votre commande a été mis à jour : PRÊTE."),
      ("Il reste 3 000 F à régler au retrait.", "Solde débiteur : 3000 FCFA."),
      ("Pas de connexion ? Continuez : tout sera envoyé dès le retour d'internet.", "Erreur réseau. Veuillez réessayer ultérieurement."),
      ("Créer mon pressing", "Démarrer le processus d'onboarding"),
      ("Ce numéro est déjà utilisé. Connectez-vous plutôt.", "Erreur 422 : contrainte d'unicité violée.")]
page("La voix", f"""
<p class="sur">06 · La voix</p><h1>Ton et rédaction</h1>
<p class="chapo">On écrit pour un gérant pressé, entre deux clients, sur un téléphone. Chaque mot doit servir.</p>
<div class="g2" style="margin-top:6mm;gap:4mm">{''.join(f'<div style="border:.3mm solid var(--trait);border-radius:4mm;padding:4mm 5mm"><h3>{t}</h3><p style="color:var(--gris);margin-top:1.5mm">{d}</p></div>' for t,d in [
 ("Simple", "Phrases courtes, mots de tous les jours. Un message, une idée."),
 ("Poli", "On vouvoie toujours. On remercie, on s'excuse quand c'est notre faute."),
 ("Concret", "Des chiffres, des dates, des actions : « prête mercredi », « 3 000 F »."),
 ("Rassurant", "On dit ce qui se passe et quoi faire ensuite. Jamais de panique.")])}</div>
<h2>On écrit… plutôt que…</h2>
<table class="tb"><tr><th>On écrit</th><th>Plutôt que</th></tr>
{''.join(f'<tr><td><span class="ok">✓</span>&nbsp; {a}</td><td style="color:var(--doux)"><span class="ko">✕</span>&nbsp; {b}</td></tr>' for a,b in EX)}</table>
<h2>Petites règles</h2>
<ul class="l">
 <li><b>Boutons</b> : un verbe à l'infinitif ou à la première personne (« Créer mon espace », « Encaisser »).</li>
 <li><b>Pas d'anglais</b> quand un mot français existe : « tableau de bord », pas « dashboard ».</li>
 <li><b>Ponctuation française</b> : espace avant « ? ! : ; », guillemets « ».</li>
 <li><b>Émojis</b> : rares, seulement sur les réseaux sociaux et WhatsApp, jamais dans l'application.</li>
</ul>""")

# ================================================================ 07 mouvement
section("07", "Le mouvement", "Le logo prend vie en un geste : le L vient s'accrocher au P.")
def frame(t, w="34mm"):
    doc = motion.html("logo-anime-clair", t)
    s = re.search(r"<svg.*</svg>", doc, re.S).group(0)
    k = f"c{int(t * 100)}"
    s = s.replace('id="c"', f'id="{k}"').replace("url(#c)", f"url(#{k})")
    return s.replace("<svg ", f'<svg style="width:{w};overflow:visible" ', 1)
page("Le mouvement", f"""
<p class="sur">07 · Le mouvement</p><h1>Le logo animé</h1>
<p class="chapo">Une animation courte (1,9 s, puis le logo reste) qui raconte l'idée du logo : le pressing se pose, le lien arrive et s'accroche.</p>
<div class="g3" style="margin-top:7mm;gap:3mm">{''.join(f'<div><div class="case" style="height:26mm;padding:2mm">{frame(t, "48mm")}</div><p class="leg">{str(t).replace(".", ",")} s</p></div>' for t in (0.1, 0.5, 0.8, 1.0, 1.4, 2.2))}</div>
<table class="tb" style="margin-top:6mm"><tr><th>Temps</th><th>Ce qui se passe</th><th>Courbe</th></tr>
<tr><td>0 – 0,55 s</td><td>Le P se déplie depuis sa base, avec un léger rebond.</td><td>sortie avec rebond</td></tr>
<tr><td>0,35 – 0,95 s</td><td>Le L remonte et vient se placer sous la barre.</td><td>sortie douce</td></tr>
<tr><td>0,8 – 1,25 s</td><td>L'accroche jaillit dans la boucle et se clipse.</td><td>rebond marqué</td></tr>
<tr><td>1,15 – 1,9 s</td><td>Le nom se dévoile de gauche à droite.</td><td>sortie douce</td></tr></table>
<h2>Principes de mouvement</h2>
<ul class="l">
 <li><b>Court</b> : 150 à 300 ms pour l'interface, moins de 2 s pour le logo.</li>
 <li><b>Doux</b> : les éléments arrivent vite et ralentissent en se posant.</li>
 <li><b>Utile</b> : une animation montre un changement (commande prête, paiement reçu), jamais pour décorer.</li>
 <li><b>Respectueux</b> : si l'utilisateur a demandé moins d'animations sur son téléphone, on les coupe.</li>
</ul>
<p class="petit" style="margin-top:4mm">Fichiers : <b>motion/logo-anime-clair</b>, <b>logo-anime-nuit</b> et <b>symbole-anime-tuile</b>, en MP4 (vidéo, réseaux sociaux) et en GIF (e-mails, messagerie).</p>""")

# ================================================================ 08 applications
section("08", "Les applications", "La marque sur l'écran, au comptoir et dans la rue.")
page("Les applications", f"""
<p class="sur">08 · Les applications</p><h1>Icône d'application et favicon</h1>
<p class="chapo">L'icône est le premier contact avec PressLink sur un téléphone : le symbole blanc et nuit sur la tuile bleue.</p>
<div style="display:flex;gap:7mm;align-items:flex-end;margin-top:8mm">
 {''.join(f'<div style="text-align:center">{img("icones/app/icone-app-1024.png", f"width:{w}mm;border-radius:22%;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.4)")}<p class="leg">{t}</p></div>' for w,t in [(42,"512 px · boutique"),(24,"180 px · iPhone"),(15,"96 px"),(9,"48 px")])}
 <div style="text-align:center">{svg("favicon.svg","width:7mm;height:7mm",dossier="icones")}<p class="leg">Favicon</p></div>
</div>
<div style="margin-top:8mm;background:linear-gradient(160deg,#1E3A8A,#0B1530);border-radius:6mm;padding:7mm;display:grid;grid-template-columns:repeat(5,1fr);gap:5mm;color:#fff;font-size:.72rem;text-align:center">
 {''.join(f'<div><div style="width:15mm;height:15mm;margin:0 auto;border-radius:22%;background:{c}"></div><p style="margin-top:1.5mm">{n}</p></div>' for c,n in [("#22C55E","Messages"),("#F97316","Photos"),("#0EA5E9","Météo"),("#EAB308","Notes")])}
 <div>{img("icones/app/icone-app-1024.png","width:15mm;margin:0 auto;border-radius:22%")}<p style="margin-top:1.5mm;font-weight:700">PressLink</p></div>
</div>
<table class="tb" style="margin-top:7mm"><tr><th>Fichier</th><th>Taille</th><th>Usage</th></tr>
<tr><td>favicon.ico · favicon.svg</td><td>16, 32, 48 px</td><td>Onglet du navigateur (coupe petite)</td></tr>
<tr><td>apple-touch-icon.png</td><td>180 px</td><td>Raccourci sur l'écran d'accueil iPhone</td></tr>
<tr><td>Icon-192 / Icon-512</td><td>192, 512 px</td><td>Application installée (Android, ordinateur)</td></tr>
<tr><td>Icon-maskable-192 / 512</td><td>192, 512 px</td><td>Icône adaptative Android (marge de sécurité)</td></tr>
<tr><td>notification-badge</td><td>96 px</td><td>Petite icône blanche des notifications</td></tr>
<tr><td>icone-app-1024</td><td>1024 px</td><td>Boutiques d'applications, présentations</td></tr></table>""")

page("Les applications", f"""
<p class="sur">08 · Les applications</p><h1>Papeterie</h1>
<h2>Carte de visite · 85 × 55 mm</h2>
<div class="g2" style="margin-top:4mm">{img("crea/carte-visite-recto.png","width:100%;border-radius:2mm;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.45)")}{img("crea/carte-visite-verso.png","width:100%;border-radius:2mm;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.45)")}</div>
<table class="tb"><tr><th>Élément</th><th>Spécification</th></tr>
<tr><td>Recto</td><td>Fond encre, motif à 3 %, halo, logo horizontal négatif (13 mm de haut), descripteur.</td></tr>
<tr><td>Verso</td><td>Fond blanc, symbole couleur, nom en Bricolage Grotesque 800, fonction en bleu, coordonnées en Plus Jakarta Sans.</td></tr>
<tr><td>Papier</td><td>Couché mat 350 g, pelliculage mat recto verso conseillé.</td></tr>
<tr><td>Impression</td><td>Quadrichromie, fond perdu de 3 mm, textes à 4 mm du bord au minimum.</td></tr></table>
<h2>Signature e-mail</h2>
<div class="case" style="height:30mm;margin-top:4mm;justify-items:start;padding:0 6mm">{img("crea/signature-email.png","height:22mm")}</div>
<p class="petit" style="margin-top:3mm">Le modèle HTML <b>crea/signature-email.html</b> se colle dans Gmail ou Outlook. Il utilise Arial (lisible partout) et le logo hébergé sur presslink.org. Pas de bannière ni de citation sous la signature.</p>""")

page("Les applications", f"""
<p class="sur">08 · Les applications</p><h1>Au comptoir du pressing</h1>
<p class="chapo">Les supports que le client touche : ils doivent rassurer et dire « votre linge est suivi ».</p>
<div style="display:grid;grid-template-columns:1fr .78fr 1.25fr;gap:5mm;margin-top:7mm;align-items:start">
 <div>{img("crea/ticket-depot.png","width:100%;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.45)")}<p class="leg">Ticket de dépôt · 80 mm</p></div>
 <div>{img("crea/etiquette-vetement.png","width:100%;border-radius:3mm;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.45)")}<p class="leg">Étiquette vêtement · 50 × 90 mm</p></div>
 <div>{img("crea/autocollant-vitrine.png","width:100%")}<p class="leg">Autocollant vitrine · Ø 200 mm</p></div>
</div>
<table class="tb" style="margin-top:6mm"><tr><th>Support</th><th>Règles</th></tr>
<tr><td><b>Ticket</b></td><td>Imprimante thermique : logo <b>noir</b> uniquement, police à chasse fixe pour aligner les montants, QR code de suivi en bas.</td></tr>
<tr><td><b>Étiquette</b></td><td>Carton 300 g ou synthétique résistant à l'eau, œillet à gauche, numéro de commande très grand pour être lu de loin.</td></tr>
<tr><td><b>Autocollant</b></td><td>Vinyle adhésif découpé à la forme, pose intérieure ou extérieure de la vitrine, à hauteur des yeux près de la porte.</td></tr></table>""")

TAILLES = [("Photo de profil", "1080 × 1080", "Symbole sur tuile bleue · rogné en rond"), ("Couverture Facebook", "1640 × 624", "Contenu au centre-gauche, rien sous la photo de profil"),
           ("Bannière LinkedIn", "1584 × 396", "Logo et signature à gauche"), ("Post carré", "1080 × 1080", "Logo en haut, message en bas"),
           ("Story / statut WhatsApp", "1080 × 1920", "Marges de 250 px en haut et en bas"), ("Image de partage (lien)", "1200 × 630", "Logo + promesse, centré")]
page("Les applications", f"""
<p class="sur">08 · Les applications</p><h1>Communication</h1>
<div style="display:grid;grid-template-columns:62mm 1fr;gap:6mm;margin-top:6mm;align-items:start">
 <div>{img("crea/flyer-a5.png","width:100%;box-shadow:0 3mm 6mm -3mm rgba(15,23,42,.45)")}<p class="leg">Flyer A5 · prospection des pressings</p></div>
 <div style="display:grid;gap:4mm">{img("crea/reseaux-banniere-facebook.png","width:100%;border-radius:2mm")}<div class="g2" style="gap:4mm">{img("crea/reseaux-post-carre.png","width:100%;border-radius:2mm")}{img("crea/image-partage-og.png","width:100%;border-radius:2mm;align-self:start")}</div></div>
</div>
<h2>Formats des réseaux sociaux</h2>
<table class="tb"><tr><th>Format</th><th>Pixels</th><th>Mise en page</th></tr>
{''.join(f'<tr><td><b>{a}</b></td><td>{b}</td><td>{c}</td></tr>' for a,b,c in TAILLES)}</table>
<div class="encart"><h3>Recette d'un visuel PressLink</h3><p>Un fond (blanc, fond clair, encre ou bleu) · un logo · un titre court en Bricolage Grotesque avec un mot en couleur · une phrase d'explication · une action (presslink.org). Rien de plus.</p></div>""")

# ================================================================ 09 fichiers
section("09", "Les fichiers", "Quel fichier utiliser, pour quel usage.")
page("Les fichiers", f"""
<p class="sur">09 · Les fichiers</p><h1>Fichiers et formats</h1>
<table class="tb" style="margin-top:6mm"><tr><th>Besoin</th><th>Fichier</th><th>Format</th></tr>
{''.join(f'<tr><td>{a}</td><td><b>{b}</b></td><td>{c}</td></tr>' for a,b,c in [
 ("Site web, document, présentation", "logo/presslink-horizontal-couleur", "SVG"),
 ("Fond sombre", "logo/presslink-horizontal-negatif", "SVG"),
 ("Impression une couleur, tampon, gravure", "logo/presslink-horizontal-noir", "SVG"),
 ("Format carré, affiche", "logo/presslink-empile-couleur", "SVG"),
 ("Avatar, broderie, petit espace", "logo/presslink-symbole-couleur", "SVG"),
 ("Très petite taille (16–23 px)", "logo/presslink-symbole-petit-couleur", "SVG"),
 ("Onglet du navigateur", "icones/site/favicon.ico + favicon.svg", "ICO, SVG"),
 ("Application installée", "icones/app/Icon-192, Icon-512, maskable", "PNG"),
 ("E-mails (logo en image)", "icones/site/email-logo.png", "PNG"),
 ("Photo de profil des réseaux", "crea/reseaux-photo-profil.png", "PNG"),
 ("Carte de visite, flyer, étiquette…", "crea/*.pdf", "PDF à taille réelle"),
 ("Vidéo, présentation", "motion/logo-anime-*.mp4", "MP4"),
 ("Messagerie, e-mail animé", "motion/logo-anime-*.gif", "GIF")])}</table>
<h2>Bon à savoir</h2>
<ul class="l">
 <li><b>SVG</b> : format vectoriel, net à toutes les tailles. À préférer partout où il est accepté.</li>
 <li><b>PNG</b> : image avec fond transparent, pour Word, PowerPoint, WhatsApp, réseaux sociaux.</li>
 <li><b>PDF</b> : pour l'imprimeur. Demander une épreuve avant le tirage complet.</li>
 <li>Pour un format qui manque (EPS, AI, broderie DST), demander à l'équipe : il est produit depuis les fichiers maîtres.</li>
</ul>""")

PAGES.append(("", f"""
<div style="position:absolute;width:240mm;height:240mm;left:-80mm;bottom:-120mm;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.55),transparent 65%)"></div>
<div style="position:absolute;left:18mm;right:18mm;top:50%;transform:translateY(-50%);text-align:center">
 {svg('presslink-empile-negatif.svg','height:60mm;display:block;margin:0 auto')}
 <p class="t" style="font-size:9mm;font-weight:800;margin-top:14mm">Une question sur la marque ?</p>
 <p style="font-size:1.2rem;color:#CBD5E1;margin-top:4mm">Avant d'imprimer ou de publier, écrivez-nous.</p>
 <p style="font-size:1.2rem;margin-top:8mm;line-height:1.8"><b>{MAIL}</b><br>{TEL} · appel ou WhatsApp<br><span style="color:var(--clair);font-weight:700">{SITE}</span></p>
</div>
<div style="position:absolute;left:18mm;right:18mm;bottom:14mm;text-align:center;font-size:.72rem;color:#64748B">PressLink · Charte graphique v1.0 · novembre 2026 · Ce document et le logo sont la propriété de PressLink.</div>""", True))

# ================================================================ sommaire et rendu
def sommaire():
    lignes = "".join(f'<div style="display:grid;grid-template-columns:16mm 1fr auto;align-items:baseline;padding:3.6mm 0;border-bottom:.3mm solid var(--trait)">'
                     f'<span class="num" style="font-size:5mm">{n}</span><span class="t" style="font-size:6mm;font-weight:700">{t}</span><span class="t" style="font-size:4.5mm;color:var(--doux)">{i + 1:02d}</span></div>'
                     for n, t, i in SECTIONS)
    return (f'<p class="sur">Sommaire</p><h1>Contenu</h1><div style="margin-top:10mm">'
            f'<div style="display:grid;grid-template-columns:16mm 1fr auto;padding:3.6mm 0;border-bottom:.3mm solid var(--trait)"><span></span><span class="t" style="font-size:6mm;font-weight:700">Avant-propos</span><span class="t" style="font-size:4.5mm;color:var(--doux)">03</span></div>'
            f'{lignes}</div>')

def rendre():
    total = len(PAGES)
    blocs = []
    for i, (sec, corps, sombre) in enumerate(PAGES):
        if corps == "@@SOMMAIRE@@":
            corps = sommaire()
        pied = "" if i == 0 or i == total - 1 or sombre else f'<div class="pied"><span>PressLink · Charte graphique{" · " + sec if sec else ""}</span><span>{i + 1:02d} / {total:02d}</span></div>'
        blocs.append(f'<section class="pg{" sombre" if sombre else ""}">{corps}{pied}</section>')
    return blocs

if __name__ == "__main__":
    import sys
    blocs = rendre()
    base = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
            "--virtual-time-budget=10000", "--allow-file-access-from-files"]
    tout = os.path.join(TMP, "charte-complete.html")
    open(tout, "w").write(HEAD + "".join(blocs) + "</body></html>")
    pdf = os.path.join(OUT, "PressLink-Charte-graphique-complete.pdf")
    subprocess.run(base + ["--no-pdf-header-footer", f"--print-to-pdf={pdf}", "file://" + tout],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=180)
    print("✓ PDF", len(blocs), "pages")
    if "--apercu" in sys.argv:
        apercu = os.path.join(TMP, "apercu"); os.makedirs(apercu, exist_ok=True)
        from concurrent.futures import ThreadPoolExecutor
        def shot(k):
            f = os.path.join(TMP, f"p{k:02d}.html")
            open(f, "w").write(HEAD + blocs[k] + "</body></html>")
            subprocess.run(base + [f"--user-data-dir={TMP}/prof{k % 4}", "--window-size=794,1123", f"--screenshot={apercu}/p{k + 1:02d}.png", "file://" + f],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=90)
        with ThreadPoolExecutor(4) as ex:
            list(ex.map(shot, range(len(blocs))))
        print("✓ aperçus")
