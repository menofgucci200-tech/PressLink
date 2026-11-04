"""Trois propositions de reçu PressLink (feuille A5 + ticket thermique 80 mm), en planches PNG."""
import os, re, subprocess

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "recus")
TMP = os.path.join(HERE, "_html", "recus"); os.makedirs(TMP, exist_ok=True)
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
R = "../../.."

def svg(name, style=""):
    s = open(os.path.join(HERE, "..", "logo", name)).read()
    s = re.sub(r"<title[^>]*>.*?</title>", "", s)
    return s.replace("<svg ", f'<svg style="{style}" ', 1)

# Données d'exemple (fictives)
P = dict(nom="Pressing Élégance", adresse="Cocody Angré, 7e tranche", tel="07 00 00 00 00", initiales="PÉ")
C = dict(num="PL-201100", client="Awa Kouassi", tel="07 •• •• •• 12", depot="03/11/2026 à 09:42", retrait="jeu. 5 nov.", retrait_long="Jeudi 5 novembre", par="Fatou")
ITEMS = [(2, "Chemise", "Repassage", 1000), (1, "Boubou bazin", "Nettoyage", 2500), (1, "Costume 2 pièces", "Nettoyage", 4000)]
TOTAL, PAYE, RESTE = 8500, 3000, 5500
PIECES = sum(q for q, *_ in ITEMS)
COND = "Vêtements non retirés après 30 jours : frais de garde de 100 F par jour."
fcfa = lambda n: f"{n:,}".replace(",", " ") + " F"

HEAD = """<!doctype html><html lang="fr"><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
<style>
:root{--nuit:#0F172A;--encre:#0B1530;--bleu:#2563EB;--clair:#60A5FA;--ciel:#EFF4FE;--fond:#F5F7FC;--gris:#475569;--doux:#64748B;--trait:#E2E8F0;--vert:#15803D;--ambre:#B45309}
*{box-sizing:border-box;margin:0;padding:0}
body{width:1600px;height:1180px;overflow:hidden;background:#CBD5E1;font-family:'Plus Jakarta Sans',sans-serif;color:var(--nuit);padding:46px 56px}
.t{font-family:'Bricolage Grotesque',sans-serif;letter-spacing:-.03em;line-height:1}
.entete{display:flex;justify-content:space-between;align-items:flex-end}
.entete h1{font-family:'Bricolage Grotesque';font-size:40px;font-weight:800;letter-spacing:-.03em}
.entete .sur{font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--bleu)}
.scene{display:flex;gap:44px;margin-top:30px;align-items:flex-start}
.a5{width:560px;height:794px;background:#fff;box-shadow:0 30px 60px -30px rgba(15,23,42,.55);position:relative;overflow:hidden;font-size:12px;line-height:1.45}
.ticket{width:302px;background:#fff;box-shadow:0 30px 60px -30px rgba(15,23,42,.55);position:relative;font-size:11px;line-height:1.45;filter:grayscale(1)}
.ticket::after{content:"";position:absolute;left:0;right:0;bottom:-8px;height:8px;background:radial-gradient(circle at 6px 0,transparent 6px,#fff 6.5px) -6px 0/12px 8px repeat-x}
.leg{font-size:12px;font-weight:700;color:#334155;margin-top:12px;letter-spacing:.02em}
.notes{flex:1;font-size:15px;line-height:1.55;color:#1E293B}
.notes h2{font-family:'Bricolage Grotesque';font-size:24px;font-weight:800;letter-spacing:-.02em}
.notes ul{list-style:none;margin-top:12px}
.notes li{position:relative;padding-left:20px;margin-top:9px}
.notes li::before{content:"";position:absolute;left:0;top:8px;width:9px;height:9px;border-radius:5px;background:var(--bleu)}
.notes .plus li::before{background:var(--vert)} .notes .moins li::before{background:#94A3B8}
.notes h3{font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--doux);margin-top:22px}
table{width:100%;border-collapse:collapse}
td{padding:0;vertical-align:top}
.r{text-align:right;white-space:nowrap}
.m{color:var(--doux)}
.b{font-weight:700}
.monogramme{display:inline-grid;place-items:center;border-radius:50%;background:var(--ciel);color:var(--bleu);font-family:'Bricolage Grotesque';font-weight:800}
</style></head><body>"""

def lignes_articles(style_qte="", prix_unit=True, pad="5px 0"):
    out = ""
    for q, n, s, u in ITEMS:
        sub = f'<br><span class="m" style="font-size:.88em">{s}{" · " + fcfa(u) + " l’unité" if q > 1 and prix_unit else ""}</span>'
        out += f'<tr><td style="padding:{pad};width:1%;white-space:nowrap;{style_qte}" class="m">{q} ×&nbsp;&nbsp;</td><td style="padding:{pad}"><b>{n}</b>{sub}</td><td class="r" style="padding:{pad}">{fcfa(q * u)}</td></tr>'
    return out

def signature(couleur=True, taille=11):
    logo = svg("presslink-horizontal-couleur.svg" if couleur else "presslink-horizontal-noir.svg", f"height:{taille * 2}px;vertical-align:middle")
    return f'<div style="text-align:center;color:#94A3B8;font-size:{taille * .82:.1f}px;margin-top:10px">Suivi par {logo}</div>'

# =============================================================== A · Épuré
def a_a5():
    return f"""<div class="a5" style="padding:44px 46px">
 <table><tr>
  <td><span class="monogramme" style="width:44px;height:44px;font-size:16px">{P['initiales']}</span></td>
  <td style="padding-left:12px"><p class="t" style="font-size:20px;font-weight:800">{P['nom']}</p><p class="m">{P['adresse']} · {P['tel']}</p></td>
  <td class="r"><p style="font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--doux);font-weight:700">Reçu de dépôt</p><p class="t" style="font-size:24px;font-weight:800;margin-top:4px">{C['num']}</p></td>
 </tr></table>
 <div style="border-top:2px solid var(--nuit);margin:22px 0 16px"></div>
 <table style="font-size:12px"><tr>
  <td style="width:50%"><p class="m">Client</p><p class="b" style="font-size:14px">{C['client']}</p><p class="m">{C['tel']}</p></td>
  <td><p class="m">Déposé le</p><p class="b" style="white-space:nowrap">{C['depot']}</p><p class="m" style="margin-top:6px">Reçu par</p><p class="b">{C['par']}</p></td>
  <td class="r" style="width:30%"><p class="m">Retrait prévu</p><p class="t" style="font-size:22px;font-weight:800;color:var(--bleu)">{C['retrait']}</p></td>
 </tr></table>
 <table style="margin-top:20px"><tr style="font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--doux)"><td style="padding-bottom:6px;border-bottom:1px solid var(--trait)" colspan="2">Articles · {PIECES} pièces</td><td class="r" style="padding-bottom:6px;border-bottom:1px solid var(--trait)">Montant</td></tr>
 {lignes_articles(pad="9px 0;border-bottom:1px solid #F1F5F9")}</table>
 <table style="margin-top:14px;width:58%;margin-left:auto">
  <tr><td class="m" style="padding:3px 0">Total</td><td class="r b" style="font-size:16px">{fcfa(TOTAL)}</td></tr>
  <tr><td class="m" style="padding:3px 0">Payé · Wave, 03/11</td><td class="r">− {fcfa(PAYE)}</td></tr>
  <tr><td colspan="2" style="padding-top:8px"><div style="border:1.5px solid var(--nuit);border-radius:8px;padding:9px 12px;display:flex;justify-content:space-between;align-items:center"><span class="b">Reste à payer au retrait</span><span class="t" style="font-size:20px;font-weight:800">{fcfa(RESTE)}</span></div></td></tr>
 </table>
 <p style="margin-top:8px;text-align:right"><span style="display:inline-block;border-radius:99px;padding:3px 10px;background:#FFFBEB;color:var(--ambre);font-weight:800;font-size:10px;letter-spacing:.1em;text-transform:uppercase">Acompte versé</span></p>
 <div style="position:absolute;left:46px;right:46px;bottom:40px">
  <p class="m" style="font-size:10.5px;border-top:1px dashed #CBD5E1;padding-top:10px"><b style="color:var(--nuit)">Conditions</b> · {COND}</p>
  <p class="m" style="font-size:10.5px;margin-top:8px">Présentez ce reçu pour retirer vos vêtements. Suivez votre commande sur <b style="color:var(--nuit)">presslink.org/app</b> avec votre numéro.</p>
  {signature(True, 11)}
 </div></div>"""

def a_ticket():
    return f"""<div class="ticket" style="padding:20px 18px 18px;font-family:'Plus Jakarta Sans'">
 <div style="text-align:center"><p class="t" style="font-size:17px;font-weight:800">{P['nom']}</p><p class="m" style="font-size:10px">{P['adresse']}<br>Tél. {P['tel']}</p>
 <p style="font-size:9px;letter-spacing:.18em;text-transform:uppercase;margin-top:10px;font-weight:700" class="m">Reçu de dépôt</p><p class="t" style="font-size:24px;font-weight:800">{C['num']}</p></div>
 <div style="border-top:2px solid #000;margin:10px 0 8px"></div>
 <table><tr><td class="m">Client</td><td class="r b">{C['client']}</td></tr><tr><td class="m">Téléphone</td><td class="r">{C['tel']}</td></tr><tr><td class="m">Déposé le</td><td class="r">{C['depot']}</td></tr></table>
 <div style="border:1.5px solid #000;border-radius:6px;margin:8px 0;padding:5px 8px;display:flex;justify-content:space-between;align-items:center"><span class="b">Retrait prévu</span><span class="t" style="font-size:16px;font-weight:800">{C['retrait']}</span></div>
 <table>{lignes_articles(pad="4px 0")}</table>
 <div style="border-top:1px dashed #000;margin:6px 0"></div>
 <table><tr><td class="b" style="font-size:13px">Total</td><td class="r b" style="font-size:13px">{fcfa(TOTAL)}</td></tr><tr><td class="m">Payé · Wave</td><td class="r">− {fcfa(PAYE)}</td></tr>
 <tr><td class="b" style="font-size:14px;padding-top:4px">Reste à payer</td><td class="r t" style="font-size:18px;font-weight:800;padding-top:4px">{fcfa(RESTE)}</td></tr></table>
 <div style="border-top:1px dashed #000;margin:8px 0"></div>
 <p class="m" style="font-size:9.5px;text-align:center">{COND}<br>Présentez ce reçu au retrait. Suivi : presslink.org/app</p>
 {signature(False, 10)}</div>"""

# =============================================================== B · Bandeau
def b_a5():
    return f"""<div class="a5">
 <div style="background:var(--encre);color:#fff;padding:30px 40px 26px;position:relative;overflow:hidden">
  <div style="position:absolute;width:300px;height:300px;right:-90px;top:-150px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.6),transparent 65%)"></div>
  <table style="position:relative"><tr>
   <td style="width:54px"><span class="monogramme" style="width:46px;height:46px;font-size:16px;background:#fff">{P['initiales']}</span></td>
   <td><p class="t" style="font-size:21px;font-weight:800">{P['nom']}</p><p style="color:#CBD5E1;font-size:11px">{P['adresse']} · {P['tel']}</p></td>
   <td class="r"><p style="font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--clair);font-weight:700">Reçu de dépôt</p><p class="t" style="font-size:24px;font-weight:800;margin-top:4px">{C['num']}</p></td>
  </tr></table></div>
 <div style="padding:22px 40px 0">
  <table><tr>
   <td style="width:58%;padding-right:14px"><div style="background:var(--fond);border-radius:12px;padding:12px 14px"><p class="m" style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-weight:700">Client</p><p class="b" style="font-size:15px;margin-top:2px">{C['client']}</p><p class="m">{C['tel']}</p><p class="m">Déposé le {C['depot']}</p></div></td>
   <td><div style="background:var(--bleu);color:#fff;border-radius:12px;padding:12px 14px"><p style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-weight:700;color:#DBEAFE">Retrait prévu</p><p class="t" style="font-size:22px;font-weight:800;margin-top:3px">{C['retrait_long']}</p></div></td>
  </tr></table>
  <table style="margin-top:18px">
   <tr style="background:var(--nuit);color:#fff;font-size:10px;letter-spacing:.1em;text-transform:uppercase"><td style="padding:7px 10px;border-radius:6px 0 0 6px" colspan="2">Article</td><td class="r" style="padding:7px 10px;border-radius:0 6px 6px 0">Montant</td></tr>
   {"".join(f'<tr style="background:{"#fff" if i % 2 == 0 else "var(--fond)"}"><td style="padding:8px 0 8px 10px;width:1%;white-space:nowrap" class="m">{q} ×&nbsp;&nbsp;</td><td style="padding:8px 0"><b>{n}</b><br><span class="m" style="font-size:.88em">{s}{" · " + fcfa(u) + " l’unité" if q > 1 else ""}</span></td><td class="r" style="padding:8px 10px">{fcfa(q*u)}</td></tr>' for i,(q,n,s,u) in enumerate(ITEMS))}
  </table>
  <table style="margin-top:14px"><tr>
   <td style="width:50%;padding-right:12px;vertical-align:bottom"><p class="m" style="font-size:11px">Total</p><p class="t" style="font-size:20px;font-weight:800">{fcfa(TOTAL)}</p><p class="m" style="font-size:11px;margin-top:6px">Déjà payé · Wave, 03/11 : <b style="color:var(--nuit)">{fcfa(PAYE)}</b></p></td>
   <td><div style="background:var(--nuit);color:#fff;border-radius:12px;padding:12px 16px"><p style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-weight:700;color:var(--clair)">Reste à payer au retrait</p><p class="t" style="font-size:28px;font-weight:800;margin-top:3px">{fcfa(RESTE)}</p></div></td>
  </tr></table>
 </div>
 <div style="position:absolute;left:40px;right:40px;bottom:34px">
  <p class="m" style="font-size:10.5px"><b style="color:var(--nuit)">Conditions</b> · {COND}</p>
  <table style="margin-top:12px;border-top:1px solid var(--trait)"><tr><td style="padding-top:12px;font-size:10.5px" class="m">Présentez ce reçu pour retirer vos vêtements.<br>Reçu par {C['par']} · imprimé le 03/11/2026 à 09:43</td><td class="r" style="padding-top:10px">{svg("presslink-horizontal-couleur.svg","height:24px")}</td></tr></table>
 </div></div>"""

def b_ticket():
    return f"""<div class="ticket" style="padding:0 0 18px">
 <div style="background:#000;color:#fff;padding:16px 16px 14px;text-align:center"><p class="t" style="font-size:17px;font-weight:800">{P['nom']}</p><p style="font-size:10px;color:#ddd">{P['adresse']} · {P['tel']}</p></div>
 <div style="padding:12px 16px 0">
  <table><tr><td><p style="font-size:9px;letter-spacing:.16em;text-transform:uppercase;font-weight:700" class="m">Reçu de dépôt</p><p class="t" style="font-size:22px;font-weight:800">{C['num']}</p></td>
  <td class="r" style="vertical-align:bottom"><p class="m" style="font-size:9px">Retrait</p><p class="t b" style="font-size:15px;font-weight:800">{C['retrait']}</p></td></tr></table>
  <div style="background:#eee;border-radius:6px;padding:7px 9px;margin-top:8px"><b>{C['client']}</b> · {C['tel']}<br><span class="m">Déposé le {C['depot']}</span></div>
  <table style="margin-top:6px">{lignes_articles(pad="4px 0")}</table>
  <div style="border-top:1px solid #000;margin:6px 0"></div>
  <table><tr><td>Total</td><td class="r b">{fcfa(TOTAL)}</td></tr><tr><td class="m">Payé · Wave</td><td class="r">{fcfa(PAYE)}</td></tr></table>
  <div style="background:#000;color:#fff;border-radius:6px;padding:7px 10px;margin-top:6px;display:flex;justify-content:space-between;align-items:center"><span class="b">Reste à payer</span><span class="t" style="font-size:18px;font-weight:800">{fcfa(RESTE)}</span></div>
  <p class="m" style="font-size:9.5px;text-align:center;margin-top:8px">{COND}</p>
  {signature(False, 10)}
 </div></div>"""

# =============================================================== C · Ticket à talon
def c_a5():
    return f"""<div class="a5">
 <div style="padding:34px 40px 0">
  <table><tr>
   <td><span class="monogramme" style="width:40px;height:40px;font-size:14px">{P['initiales']}</span></td>
   <td style="padding-left:10px"><p class="t" style="font-size:18px;font-weight:800">{P['nom']}</p><p class="m" style="font-size:11px">{P['adresse']} · {P['tel']}</p></td>
   <td class="r m" style="font-size:11px">Reçu de dépôt<br>{C['depot']}</td></tr></table>
  <table style="margin-top:18px"><tr>
   <td style="width:62%;padding-right:16px"><div style="border-radius:14px;background:var(--fond);padding:16px 18px">
     <p class="m" style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;font-weight:700">Commande</p>
     <p class="t" style="font-size:44px;font-weight:800;letter-spacing:-.02em">{C['num']}</p>
     <p style="margin-top:6px"><b>{C['client']}</b> <span class="m">· {C['tel']}</span></p></div></td>
   <td style="text-align:center"><img src="{R}/crea/qr-presslink-app.png" style="width:104px;height:104px"><p class="m" style="font-size:10px;margin-top:4px">Scannez pour suivre<br>votre commande</p></td>
  </tr></table>
  <table style="margin-top:16px"><tr>
   <td style="width:33%"><p class="m" style="font-size:10.5px">Retrait prévu</p><p class="t" style="font-size:18px;font-weight:800;color:var(--bleu)">{C['retrait']}</p></td>
   <td style="width:33%"><p class="m" style="font-size:10.5px">Pièces</p><p class="t" style="font-size:18px;font-weight:800">{PIECES}</p></td>
   <td><p class="m" style="font-size:10.5px">Reste à payer</p><p class="t" style="font-size:18px;font-weight:800">{fcfa(RESTE)}</p></td></tr></table>
  <table style="margin-top:14px;border-top:1px solid var(--trait)">{lignes_articles(pad="7px 0;border-bottom:1px solid #F1F5F9")}
   <tr><td colspan="2" style="padding-top:8px" class="b">Total</td><td class="r b" style="padding-top:8px;font-size:14px">{fcfa(TOTAL)}</td></tr>
   <tr><td colspan="2" class="m">Payé · Wave, 03/11</td><td class="r m">{fcfa(PAYE)}</td></tr></table>
  <p class="m" style="font-size:10px;margin-top:10px"><b style="color:var(--nuit)">Conditions</b> · {COND}</p>
 </div>
 <div style="position:absolute;left:0;right:0;bottom:0;height:170px;border-top:2px dashed #94A3B8;background:repeating-linear-gradient(90deg,transparent 0 0);padding:18px 40px">
  <span style="position:absolute;top:-11px;left:28px;background:#fff;padding:0 6px;font-size:14px;color:#94A3B8">✂</span>
  <table><tr>
   <td style="width:62%"><p class="m" style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;font-weight:700">Talon · à agrafer au sac de linge</p>
    <p class="t" style="font-size:38px;font-weight:800;margin-top:4px">{C['num']}</p>
    <p style="margin-top:4px"><b>{C['client']}</b> · {PIECES} pièces · retrait {C['retrait']}</p></td>
   <td class="r" style="vertical-align:bottom">{svg("presslink-symbole-couleur.svg","width:46px")}<p class="m" style="font-size:9.5px">{P['nom']}</p></td>
  </tr></table>
  <div style="position:absolute;left:40px;right:40px;bottom:10px">{signature(True, 10)}</div>
 </div></div>"""

def c_ticket():
    return f"""<div class="ticket" style="padding:18px 16px 14px">
 <table><tr><td><p class="t" style="font-size:15px;font-weight:800">{P['nom']}</p><p class="m" style="font-size:9.5px">{P['tel']}</p></td><td class="r m" style="font-size:9.5px">{C['depot']}</td></tr></table>
 <div style="border:2px solid #000;border-radius:8px;margin-top:10px;padding:8px 10px;text-align:center"><p style="font-size:9px;letter-spacing:.16em;text-transform:uppercase;font-weight:700">Commande</p><p class="t" style="font-size:30px;font-weight:800">{C['num']}</p><p><b>{C['client']}</b> · {C['tel']}</p></div>
 <table style="margin-top:8px;text-align:center"><tr><td><p class="m" style="font-size:9px">Retrait</p><p class="b" style="font-size:13px">{C['retrait']}</p></td><td><p class="m" style="font-size:9px">Pièces</p><p class="b" style="font-size:13px">{PIECES}</p></td><td><p class="m" style="font-size:9px">Reste</p><p class="b" style="font-size:13px">{fcfa(RESTE)}</p></td></tr></table>
 <table style="margin-top:6px;border-top:1px dashed #000">{lignes_articles(pad="4px 0")}<tr><td colspan="2" class="b" style="padding-top:4px">Total</td><td class="r b" style="padding-top:4px">{fcfa(TOTAL)}</td></tr><tr><td colspan="2" class="m">Payé · Wave</td><td class="r m">{fcfa(PAYE)}</td></tr></table>
 <table style="margin-top:8px"><tr><td style="width:64px"><img src="{R}/crea/qr-presslink-app.png" style="width:58px;height:58px"></td><td class="m" style="font-size:9.5px;vertical-align:middle">Scannez pour suivre votre commande. {COND}</td></tr></table>
 <div style="border-top:2px dashed #000;margin:12px -16px 0;position:relative"><span style="position:absolute;top:-10px;left:12px;background:#fff;padding:0 4px;font-size:12px">✂</span></div>
 <div style="text-align:center;padding-top:10px"><p style="font-size:9px;letter-spacing:.14em;text-transform:uppercase;font-weight:700" class="m">Talon · sac de linge</p><p class="t" style="font-size:26px;font-weight:800">{C['num']}</p><p style="font-size:10.5px"><b>{C['client']}</b> · {PIECES} pièces · {C['retrait']}</p></div>
 {signature(False, 9)}</div>"""

PROPS = [
    ("a", "A · Épuré", "Le reçu actuel, mis au propre", a_a5, a_ticket,
     "Une mise en page sobre, en noir et bleu, qui s'imprime aussi bien sur une imprimante de bureau que sur un ticket thermique.",
     ["Le numéro et le <b>retrait prévu</b> se lisent en premier.", "Le <b>reste à payer</b> est encadré : impossible de le rater.", "Coût d'encre minimal, rendu identique en noir et blanc."],
     ["Le moins « marqué » des trois : peu de couleur PressLink."]),
    ("b", "B · Bandeau", "La marque bien visible", b_a5, b_ticket,
     "Un bandeau nuit en tête avec le nom du pressing, des cartes pour le client et le retrait, et le reste à payer en grand sur fond nuit.",
     ["Le plus <b>professionnel</b> : on reconnaît un document PressLink.", "Idéal en <b>PDF partagé sur WhatsApp</b> : il se lit très bien sur téléphone.", "Le tableau des articles est très lisible (lignes alternées)."],
     ["Beaucoup d'aplats foncés : plus d'encre à l'impression A5.", "Sur ticket thermique, les aplats noirs usent le papier plus vite."]),
    ("c", "C · Ticket à talon", "Le plus pratique au comptoir", c_a5, c_ticket,
     "Le numéro de commande devient le héros, un QR code renvoie au suivi, et un talon détachable s'agrafe au sac de linge.",
     ["Le <b>talon</b> remplace l'étiquette écrite à la main : moins d'erreurs et de sacs mélangés.", "Le <b>QR code</b> ouvre le suivi de la commande, sans rien taper.", "Les 3 infos clés (retrait, pièces, reste) en une ligne."],
     ["Le ticket est plus long (talon) : un peu plus de papier.", "Le QR code doit pointer vers le reçu de la commande (déjà disponible en lien public)."]),
]

def planche(code, titre, sous, fa5, ft, intro, plus, moins):
    return HEAD + f"""
<div class="entete"><div><p class="sur">Proposition de reçu · PressLink</p><h1>{titre} <span style="color:var(--doux);font-weight:600;font-size:26px">— {sous}</span></h1></div>
<div>{svg("presslink-horizontal-couleur.svg","height:44px")}</div></div>
<div class="scene">
 <div>{fa5()}<p class="leg">Feuille A5 · imprimante de bureau et PDF partagé</p></div>
 <div>{ft()}<p class="leg" style="margin-top:20px">Ticket 80 mm · imprimante thermique</p></div>
 <div class="notes"><h2>L'idée</h2><p style="margin-top:8px">{intro}</p>
  <h3>Points forts</h3><ul class="plus">{''.join(f'<li>{x}</li>' for x in plus)}</ul>
  <h3>À savoir</h3><ul class="moins">{''.join(f'<li>{x}</li>' for x in moins)}</ul>
  <p style="margin-top:22px;font-size:13px;color:var(--doux)">Données fictives. Le logo du pressing (ici « PÉ ») est remplacé par celui que le gérant a ajouté dans ses paramètres.</p>
 </div></div></body></html>"""

if __name__ == "__main__":
    base = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run", "--virtual-time-budget=8000", "--allow-file-access-from-files"]
    for code, *rest in PROPS:
        page = os.path.join(TMP, f"recu-{code}.html")
        open(page, "w").write(planche(code, *rest))
        subprocess.run(base + [f"--user-data-dir={TMP}/p{code}", "--window-size=1600,1180", f"--screenshot={OUT}/proposition-recu-{code}.png", "file://" + page],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=90)
        print("✓", code)
