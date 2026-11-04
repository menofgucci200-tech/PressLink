"""Créations PressLink (HTML → PNG et PDF via Chrome sans interface)."""
import re, subprocess, os
from logo import *

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "crea")
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
TEL, MAIL, SITE, APP = "07 18 00 22 93", "contact@presslink.org", "presslink.org", "presslink.org/app"

def inline(name, style=""):
    s = open(os.path.join(HERE, "..", "logo", name)).read()
    s = re.sub(r"<title[^>]*>.*?</title>", "", s)
    return s.replace("<svg ", f'<svg style="{style}" ', 1)

def icon(name, style=""):
    s = open(os.path.join(HERE, "..", "icones", name)).read()
    s = re.sub(r"<title[^>]*>.*?</title>", "", s)
    return s.replace("<svg ", f'<svg style="{style}" ', 1)

# Motif : le symbole en filigrane, répété en quinconce.
P, L = symbole()
def motif(c1, c2, opacity=1, size=88):
    sp, sl = shift(P, 0, 0, size / 256), shift(L, 0, 0, size / 256)
    sp2, sl2 = shift(P, size, size, size / 256), shift(L, size, size, size / 256)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" style="position:absolute;inset:0;opacity:{opacity}">'
            f'<defs><pattern id="m" width="{size*2}" height="{size*2}" patternUnits="userSpaceOnUse">'
            f'<path fill="{c1}" d="{sp}"/><path fill="{c2}" d="{sl}"/><path fill="{c1}" d="{sp2}"/><path fill="{c2}" d="{sl2}"/>'
            f'</pattern></defs><rect width="100%" height="100%" fill="url(#m)"/></svg>')
open(os.path.join(OUT, "motif-presslink.svg"), "w").write(
    motif("#DBE7FD", "#BFD3FB", 1, 88).replace('width="100%" height="100%" style="position:absolute;inset:0;opacity:1"', 'width="704" height="704" viewBox="0 0 704 704"'))

HEAD = """<!doctype html><html lang="fr"><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<style>
:root{--nuit:#0F172A;--encre:#0B1530;--bleu:#2563EB;--clair:#60A5FA;--ciel:#EFF4FE;--vert:#16A34A;--or:#FBBF24;--fond:#F5F7FC;--gris:#475569;}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:WIDTH;height:HEIGHT;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--nuit);-webkit-print-color-adjust:exact;print-color-adjust:exact}
.t{font-family:'Bricolage Grotesque',sans-serif;letter-spacing:-.035em;line-height:1}
.halo{position:absolute;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.55),transparent 65%)}
@page{size:WIDTH HEIGHT;margin:0}
</style></head><body>"""

def page(w, h, body, unit="px"):
    return HEAD.replace("WIDTH", f"{w}{unit}").replace("HEIGHT", f"{h}{unit}") + body + "</body></html>"

CREAS = {}

# ---------- Carte de visite 85 × 55 mm
CREAS["carte-visite-recto"] = (85, 55, "mm", f"""
<div style="position:relative;width:85mm;height:55mm;background:var(--encre);overflow:hidden;color:#fff">
  <div class="halo" style="width:70mm;height:70mm;right:-25mm;top:-30mm"></div>
  {motif('rgba(255,255,255,.035)', 'rgba(96,165,250,.07)', 1, 46)}
  <div style="position:absolute;left:7mm;top:50%;transform:translateY(-50%)">{inline('presslink-horizontal-negatif.svg', 'height:13mm;display:block')}
  <p style="margin-top:3mm;font-size:2.6mm;color:#CBD5E1;font-weight:600">Le lien entre votre pressing et vos clients</p></div>
</div>""")
CREAS["carte-visite-verso"] = (85, 55, "mm", f"""
<div style="position:relative;width:85mm;height:55mm;background:#fff;overflow:hidden;padding:7mm">
  <div style="position:absolute;right:-9mm;bottom:-12mm;opacity:.08">{inline('presslink-symbole-noir.svg', 'width:52mm')}</div>
  {inline('presslink-symbole-couleur.svg', 'width:11mm;display:block;margin-left:-1.6mm')}
  <p class="t" style="font-size:5.2mm;font-weight:800;margin-top:3mm">Prénom Nom</p>
  <p style="font-size:2.7mm;color:var(--bleu);font-weight:700;margin-top:1mm">Fonction · PressLink</p>
  <div style="position:absolute;left:7mm;bottom:6.5mm;font-size:2.6mm;line-height:1.65;color:#334155;font-weight:500">
    <div><b style="color:var(--nuit)">Tél. / WhatsApp</b> &nbsp;{TEL}</div><div><b style="color:var(--nuit)">E-mail</b> &nbsp;{MAIL}</div><div><b style="color:var(--nuit)">Site</b> &nbsp;{SITE}</div>
  </div>
</div>""")

# ---------- Étiquette vêtement 50 × 90 mm (trou en haut)
CREAS["etiquette-vetement"] = (50, 90, "mm", f"""
<div style="position:relative;width:50mm;height:90mm;background:#fff;border-radius:4mm;overflow:hidden;border:0.3mm solid #E2E8F0">
  <div style="height:22mm;background:var(--bleu);position:relative;overflow:hidden">
    <div style="position:absolute;top:50%;left:6mm;width:5mm;height:5mm;margin-top:-2.5mm;border-radius:50%;background:#fff;box-shadow:inset 0 0 0 0.8mm #1D4ED8"></div>
    <div style="position:absolute;top:50%;right:6mm;transform:translateY(-50%)">{inline('presslink-symbole-negatif.svg', 'width:13mm;display:block')}</div>
  </div>
  <div style="padding:5mm 5mm 0;text-align:center">
    <p style="font-size:2.4mm;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gris)">Commande</p>
    <p class="t" style="font-size:12mm;font-weight:800;margin-top:1mm">0427</p>
    <div style="margin:3.5mm 0;border-top:0.3mm dashed #CBD5E1"></div>
    <div style="font-size:2.7mm;color:#334155;text-align:left;display:grid;gap:2.6mm"><div style="display:flex;gap:1.5mm"><span>Client</span><span style="flex:1;border-bottom:0.3mm solid #94A3B8"></span></div><div style="display:flex;gap:1.5mm"><span>Prête le</span><span style="flex:1;border-bottom:0.3mm solid #94A3B8"></span></div></div>
    <div style="margin:3.5mm 0;border-top:0.3mm dashed #CBD5E1"></div>
    <p style="font-size:2.5mm;color:var(--gris);line-height:1.4">Vous serez prévenu<br>dès que c'est prêt.</p>
  </div>
  <div style="position:absolute;left:0;right:0;bottom:5mm;display:flex;justify-content:center">{inline('presslink-horizontal-couleur.svg', 'height:5.5mm')}</div>
</div>""")

# ---------- Ticket de dépôt 80 mm
CREAS["ticket-depot"] = (80, 130, "mm", f"""
<div style="width:80mm;height:130mm;background:#fff;padding:7mm 6mm;font:500 2.9mm/1.6 ui-monospace,Menlo,monospace;color:#111827">
  <div style="display:flex;justify-content:center">{inline('presslink-horizontal-noir.svg', 'height:8mm')}</div>
  <p style="text-align:center;margin-top:2.5mm">Pressing Élégance · Cocody Angré<br>07 00 00 00 00</p>
  <p style="border-top:0.3mm dashed #111;margin:3mm 0"></p>
  <div style="display:flex;justify-content:space-between;"><span>Ticket n° 0427</span><span>03/11/2026 09:42</span></div><div style="display:flex;justify-content:space-between;"><span>Client : Awa K.</span><span>07 •• •• •• 12</span></div>
  <p style="border-top:0.3mm dashed #111;margin:3mm 0"></p>
  <div style="display:flex;justify-content:space-between;"><span>2 × Chemise · Repassage</span><span>2 000 F</span></div><div style="display:flex;justify-content:space-between;"><span>1 × Boubou · Nettoyage</span><span>2 500 F</span></div><div style="display:flex;justify-content:space-between;"><span>1 × Pantalon · Nettoyage</span><span>1 500 F</span></div>
  <p style="border-top:0.3mm dashed #111;margin:3mm 0"></p>
  <div style="display:flex;justify-content:space-between;font-weight:800;"><span>TOTAL</span><span>6 000 F</span></div><div style="display:flex;justify-content:space-between;"><span>Payé (Wave)</span><span>3 000 F</span></div><div style="display:flex;justify-content:space-between;font-weight:800;"><span>Reste à payer</span><span>3 000 F</span></div>
  <p style="border-top:0.3mm dashed #111;margin:3mm 0"></p>
  <p style="text-align:center">Prête le mercredi 05/11.<br>Vous serez prévenu sur votre téléphone.</p>
  <div style="display:flex;align-items:center;gap:3mm;margin-top:4mm;justify-content:center">
    <img src="../../crea/qr-presslink-app.png" style="width:18mm;height:18mm"><p style="line-height:1.4">Suivez votre<br>commande sur<br><b>{APP}</b></p></div>
</div>""")

# ---------- Autocollant vitrine rond 200 mm
CREAS["autocollant-vitrine"] = (200, 200, "mm", f"""
<div style="position:relative;width:200mm;height:200mm;border-radius:50%;background:var(--bleu);overflow:hidden;color:#fff;text-align:center">
  {motif('rgba(255,255,255,.045)', 'rgba(15,23,42,.07)', 1, 120)}
  <div style="position:absolute;inset:14mm;border-radius:50%;border:1.2mm dashed rgba(255,255,255,.45)"></div>
  <div style="position:absolute;inset:0;display:grid;place-items:center"><div>
    <div style="display:inline-block;background:#fff;border-radius:9mm;padding:5mm">{inline('presslink-symbole-couleur.svg', 'width:34mm;display:block')}</div>
    <p class="t" style="font-size:15mm;font-weight:800;margin-top:7mm">Ici, votre linge<br>est suivi.</p>
    <p style="font-size:6mm;font-weight:600;margin-top:5mm;color:#DBEAFE">Vous êtes prévenu dès que c'est prêt.</p>
    <div style="display:inline-flex;align-items:center;gap:4mm;margin-top:8mm;background:#fff;color:var(--nuit);border-radius:5mm;padding:3mm 5mm 3mm 3mm">
      <img src="../../crea/qr-presslink-app.png" style="width:22mm;height:22mm"><p style="text-align:left;font-size:5mm;line-height:1.3;font-weight:600">Suivez votre<br>commande<br><b style="color:var(--bleu)">{APP}</b></p></div>
  </div></div>
</div>""")

# ---------- Flyer A5 (pressings)
CREAS["flyer-a5"] = (148, 210, "mm", f"""
<div style="position:relative;width:148mm;height:210mm;background:var(--fond);overflow:hidden;padding:12mm">
  <div class="halo" style="width:150mm;height:150mm;right:-60mm;top:-50mm;opacity:.5"></div>
  <div style="position:relative">{inline('presslink-horizontal-couleur.svg', 'height:11mm;display:block')}</div>
  <p style="position:relative;margin-top:11mm;display:inline-block;background:#fff;border:0.3mm solid #E2E8F0;border-radius:99mm;padding:1.6mm 4mm;font-size:3.4mm;font-weight:700;color:var(--gris)">Logiciel de gestion de pressing</p>
  <h1 class="t" style="position:relative;font-size:15mm;font-weight:800;margin-top:6mm">Votre pressing,<br>enfin <span style="color:var(--bleu);background:linear-gradient(transparent 78%,rgba(251,191,36,.5) 78%,rgba(251,191,36,.5) 92%,transparent 92%)">simple.</span></h1>
  <p style="position:relative;font-size:4.4mm;line-height:1.5;color:var(--gris);margin-top:5mm;max-width:110mm">Commandes, paiements et clients prévenus au même endroit, sur téléphone, tablette ou ordinateur.</p>
  <div style="position:relative;display:grid;gap:3mm;margin-top:8mm">
    {''.join(f'''<div style="display:flex;align-items:center;gap:4mm;background:{bg};color:{fg};border:0.4mm solid {bd};border-radius:5mm;padding:3.2mm 4.5mm"><span class="t" style="width:10mm;height:10mm;border-radius:50%;display:grid;place-items:center;background:{pb};color:{pf};font-weight:800;font-size:5mm">{n}</span><div><p class="t" style="font-size:6.4mm;font-weight:700">{t}</p><p style="font-size:3.6mm;margin-top:1mm;opacity:.85">{d}</p></div></div>''' for n,t,d,bg,fg,bd,pb,pf in [
      ("1","Testez","Un pressing fictif, sans inscription.","#fff","var(--nuit)","#E2E8F0","var(--ciel)","var(--bleu)"),
      ("2","Créez votre pressing","2 minutes. On vous appelle pour démarrer.","var(--bleu)","#fff","var(--bleu)","#fff","var(--bleu)"),
      ("3","Choisissez votre formule","Dès 20 000 F par mois, sans engagement.","#fff","var(--nuit)","#E2E8F0","var(--ciel)","var(--bleu)")])}
  </div>
  <div style="position:absolute;left:12mm;right:12mm;bottom:12mm;display:flex;align-items:center;justify-content:space-between;background:var(--encre);color:#fff;border-radius:6mm;padding:5mm 6mm">
    <div><p class="t" style="font-size:7mm;font-weight:800">{SITE}</p><p style="font-size:3.6mm;color:#CBD5E1;margin-top:1mm">Appel ou WhatsApp : {TEL}</p></div>
    {icon('icone-app-arrondie.svg', 'width:15mm')}
  </div>
</div>""")

# ---------- Réseaux sociaux
CREAS["reseaux-photo-profil"] = (1080, 1080, "px", f"""<div style="width:1080px;height:1080px">{icon('icone-app.svg', 'width:1080px;height:1080px;display:block')}</div>""")

def banniere(w, h, titre_px, logo_h):
    return f"""
<div style="position:relative;width:{w}px;height:{h}px;background:var(--encre);overflow:hidden;color:#fff">
  <div class="halo" style="width:{h*2}px;height:{h*2}px;right:-{h*0.5}px;top:-{h*0.7}px"></div>
  {motif('rgba(255,255,255,.03)', 'rgba(96,165,250,.06)', 1, max(60, h // 5))}
  <div style="position:absolute;left:{h*0.32}px;top:50%;transform:translateY(-50%)">
    {inline('presslink-horizontal-negatif.svg', f'height:{logo_h}px;display:block')}
    <p class="t" style="font-size:{titre_px}px;font-weight:800;margin-top:{titre_px*0.5}px">Votre pressing, enfin <span style="color:var(--clair)">simple.</span></p>
    <p style="font-size:{titre_px*0.42}px;color:#CBD5E1;font-weight:600;margin-top:{titre_px*0.3}px">Commandes · paiements · clients prévenus  —  {SITE}</p>
  </div>
</div>"""
CREAS["reseaux-banniere-facebook"] = (1640, 624, "px", banniere(1640, 624, 74, 92))
CREAS["reseaux-banniere-linkedin"] = (1584, 396, "px", banniere(1584, 396, 50, 64))
CREAS["image-partage-og"] = (1200, 630, "px", banniere(1200, 630, 64, 88))

CREAS["reseaux-post-carre"] = (1080, 1080, "px", f"""
<div style="position:relative;width:1080px;height:1080px;background:var(--fond);overflow:hidden;padding:90px">
  <div class="halo" style="width:1000px;height:1000px;right:-380px;top:-380px;opacity:.45"></div>
  {inline('presslink-horizontal-couleur.svg', 'height:70px;display:block;position:relative')}
  <div style="position:relative;margin-top:110px;background:#fff;border-radius:44px;box-shadow:0 40px 80px -40px rgba(15,23,42,.45);padding:40px;display:flex;gap:30px;align-items:center">
    {icon('icone-app-arrondie.svg', 'width:120px;flex:none')}
    <div><p style="font-size:26px;color:var(--gris);font-weight:600">PressLink · maintenant</p><p class="t" style="font-size:46px;font-weight:800;margin-top:8px">Votre commande est prête !</p><p style="font-size:28px;color:var(--gris);margin-top:8px">Pressing Élégance · 3 000 F à régler au retrait</p></div>
  </div>
  <div style="position:absolute;left:90px;right:90px;bottom:90px"><p class="t" style="font-size:104px;font-weight:800">Vos clients prévenus,<br><span style="color:var(--bleu)">sans un appel.</span></p><p style="font-size:32px;color:var(--gris);font-weight:600;margin-top:34px">Dès que la commande est prête  ·  presslink.org</p></div>
</div>""")

CREAS["reseaux-story"] = (1080, 1920, "px", f"""
<div style="position:relative;width:1080px;height:1920px;background:var(--bleu);overflow:hidden;color:#fff;padding:120px 90px">
  {motif('rgba(255,255,255,.045)', 'rgba(15,23,42,.08)', 1, 150)}
  <div style="position:relative;display:inline-block;background:#fff;border-radius:48px;padding:34px">{inline('presslink-symbole-couleur.svg', 'width:190px;display:block')}</div>
  <p class="t" style="position:relative;font-size:150px;font-weight:800;margin-top:120px">Votre<br>pressing,<br>enfin<br><span style="color:var(--encre)">simple.</span></p>
  <p style="position:relative;font-size:44px;line-height:1.45;font-weight:600;color:#DBEAFE;margin-top:70px">Commandes, paiements et clients prévenus au même endroit.</p>
  <div style="position:absolute;left:90px;right:90px;bottom:120px;background:#fff;color:var(--nuit);border-radius:40px;padding:40px 46px;display:flex;justify-content:space-between;align-items:center">
    <p class="t" style="font-size:60px;font-weight:800">{SITE}</p><span style="width:96px;height:96px;border-radius:50%;background:var(--vert);color:#fff;display:grid;place-items:center;font-size:52px">→</span></div>
</div>""")

# ---------- Signature e-mail (aperçu ; le HTML seul est écrit à part)
SIGNATURE = f"""<table cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif;color:#0F172A"><tr>
<td style="padding-right:16px;border-right:3px solid #2563EB"><img src="https://presslink.org/images/logo-presslink.png" width="64" height="64" alt="PressLink" style="display:block"></td>
<td style="padding-left:16px;font-size:13px;line-height:1.5"><b style="font-size:15px">Prénom Nom</b><br><span style="color:#2563EB;font-weight:bold">Fonction · PressLink</span><br>
<span style="color:#475569">{TEL} · <a href="mailto:{MAIL}" style="color:#475569">{MAIL}</a> · <a href="https://{SITE}" style="color:#2563EB;font-weight:bold;text-decoration:none">{SITE}</a></span><br>
<span style="color:#64748B;font-size:12px">Le lien entre votre pressing et vos clients</span></td></tr></table>"""
open(os.path.join(OUT, "signature-email.html"), "w").write(SIGNATURE)
CREAS["signature-email"] = (640, 140, "px", f'<div style="padding:24px;background:#fff">{SIGNATURE.replace("https://presslink.org/images/logo-presslink.png", "../../icones/site/logo-presslink.png")}</div>')

PRINT = {"carte-visite-recto", "carte-visite-verso", "etiquette-vetement", "ticket-depot", "autocollant-vitrine", "flyer-a5"}
MM = 3.7795

def render(name, w, h, unit, body):
    html = os.path.join(HERE, "_html", f"{name}.html"); os.makedirs(os.path.dirname(html), exist_ok=True)
    open(html, "w").write(page(w, h, body, unit))
    pw, ph = (round(w * MM), round(h * MM)) if unit == "mm" else (w, h)
    scale = 1
    if unit == "mm":
        scale = max(1, 1600 / max(pw, ph))  # aperçu PNG d'environ 1600 px
    base = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
            "--virtual-time-budget=6000", "--allow-file-access-from-files"]
    subprocess.run(base + [f"--window-size={pw},{ph}", f"--force-device-scale-factor={scale:.3f}",
                           f"--screenshot={os.path.join(OUT, name + '.png')}", "file://" + html],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=90)
    if name in PRINT:
        subprocess.run(base + ["--no-pdf-header-footer", f"--print-to-pdf={os.path.join(OUT, name + '.pdf')}", "file://" + html],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=90)

if __name__ == "__main__":
    import sys
    only = set(sys.argv[1:])
    for name, (w, h, unit, body) in CREAS.items():
        if only and name not in only:
            continue
        render(name, w, h, unit, body)
        print("✓", name)
