"""Fabrique les deux propositions motion à partir de la vraie page d'accueil.

Usage : python fabriquer.py <landing.html>
Les adresses sont réécrites vers les copies locales (commun/) ; chaque
proposition ajoute seulement son motion.css et son motion.js.
"""
import pathlib
import sys

ICI = pathlib.Path(__file__).parent
html = pathlib.Path(sys.argv[1]).read_text()
for ancien, nouveau in [
    ('http://127.0.0.1:8000/build/assets/app-xLvGu_PU.css', '../commun/app.css'),
    ('http://127.0.0.1:8000/images/', '../commun/img/'),
    ('http://127.0.0.1:8000/qr-presslink-app.png', '../commun/img/qr-presslink-app.png'),
    ('http://127.0.0.1:8000/video/', '../commun/video/'),
    ('http://127.0.0.1:8000/apple-touch-icon.png', '../commun/img/apple-touch-icon.png'),
    ('http://localhost:8000/app/', 'https://presslink.org/app/'),
    ('http://localhost:8000/', 'https://presslink.org/'),
    ('http://127.0.0.1:8000/', 'https://presslink.org/'),
    ('href="http://127.0.0.1:8000"', 'href="https://presslink.org/"'),
]:
    html = html.replace(ancien, nouveau)
for dossier, nom in (('1-fluide', 'Proposition 1 · Fluide'), ('2-raconte', 'Proposition 2 · Raconté')):
    page = html.replace('</head>', '<link rel="stylesheet" href="../commun/fonts.css"><link rel="stylesheet" href="motion.css">\n</head>')
    on1 = ' class="on"' if dossier == '1-fluide' else ''
    on2 = ' class="on"' if dossier == '2-raconte' else ''
    barre = ('<nav class="prop-bar"><a href="../">Propositions</a>'
             f'<a{on1} href="../1-fluide/">1 · Fluide</a><a{on2} href="../2-raconte/">2 · Raconté</a></nav>')
    scripts = ('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>\n'
               '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>\n'
               '<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js"></script>\n'
               '<script src="motion.js"></script>\n')
    page = page.replace('</body>', barre + '\n' + scripts + '</body>')
    (ICI / dossier / 'index.html').write_text(page)
    print('→', dossier)
