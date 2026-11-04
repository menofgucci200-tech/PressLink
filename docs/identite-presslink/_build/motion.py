"""Animation du logo : le P se pose, le L remonte sous la barre, l'accroche se clipse dans la boucle, puis le nom apparaît."""
import os, re, subprocess, shutil
from concurrent.futures import ThreadPoolExecutor
from logo import *

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "motion")
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
FFMPEG = "/opt/homebrew/bin/ffmpeg"
FPS, DUREE = 30, 3.4

P, L = symbole()
accroche, bas = re.split(r"(?= M)", L, maxsplit=1)
wp, wl, WW = mot()
s = 0.92
hp, hl = shift(wp, 266 - 26 * s, 128 - 130 * s, s), shift(wl, 266 - 26 * s, 128 - 130 * s, s)
LARG = 266 + (WW - 52) * s + 42

def clamp(x): return max(0.0, min(1.0, x))
def out_cubic(x): return 1 - (1 - x) ** 3
def out_quint(x): return 1 - (1 - x) ** 5
def out_back(x, k=1.70158):
    return 1 + (k + 1) * (x - 1) ** 3 + k * (x - 1) ** 2
def phase(t, debut, duree): return clamp((t - debut) / duree)

def etat(t):
    """Transformations SVG de chaque pièce à l'instant t (secondes)."""
    a = phase(t, 0.0, 0.55); ea = out_back(a, 1.3)
    p = (f'transform="translate(42 224) scale({max(0.001, 0.4 + 0.6 * ea):.4f} {max(0.001, ea):.4f}) translate(-42 -224)" '
         f'opacity="{clamp(a * 2.5):.3f}"')
    b = phase(t, 0.35, 0.6); eb = out_quint(b)
    bas_ = f'transform="translate(0 {90 * (1 - eb):.2f})" opacity="{clamp(b * 2.5):.3f}"'
    c = phase(t, 0.8, 0.45); ec = out_back(c, 2.2)
    acc = (f'transform="translate(120 116) translate(0 {34 * (1 - ec):.2f}) scale(1 {max(0.001, 0.2 + 0.8 * ec):.4f}) translate(-120 -116)" '
           f'opacity="{clamp(c / 0.6):.3f}"')
    d = phase(t, 1.15, 0.75); ed = out_quint(d)
    return p, bas_, acc, ed

VARIANTES = {
    # nom: (largeur, hauteur, fond, couleur P, couleur L, avec le nom, largeur du logo)
    "logo-anime-clair": (1920, 1080, "#FFFFFF", NUIT, BLEU, True, 1240),
    "logo-anime-nuit": (1920, 1080, "#0B1530", BLANC, CLAIR, True, 1240),
    "symbole-anime-tuile": (1080, 1080, BLEU, BLANC, NUIT, False, 640),
}

def html(var, t):
    w, h, bg, c1, c2, nom, wpx = VARIANTES[var]
    view = f"0 0 {f(LARG)} 256" if nom else "0 0 256 256"
    tp, tb, ta, ed = etat(t)
    parts = (f'<path {tp} fill="{c1}" d="{P}"/><path {tb} fill="{c2}" d="{bas}"/>'
             f'<path {ta} fill="{c2}" d="{accroche}"/>')
    if nom:
        x0 = 246
        parts += (f'<clipPath id="c"><rect x="{x0}" y="0" width="{(LARG - x0 + 20) * ed:.2f}" height="256"/></clipPath>'
                  f'<g clip-path="url(#c)"><g transform="translate({-36 * (1 - ed):.2f} 0)"><path fill="{c1}" d="{hp}"/><path fill="{c2}" d="{hl}"/></g></g>')
    return (f'<!doctype html><html><head><meta charset="utf-8"><style>*{{margin:0}}html,body{{width:100%;height:100%;overflow:hidden;background:{bg}}}'
            f'body{{display:grid;place-items:center}}svg{{width:{wpx}px;overflow:visible}}</style></head>'
            f'<body><svg xmlns="http://www.w3.org/2000/svg" viewBox="{view}">{parts}</svg></body></html>')

def frame(args):
    var, i, tmp = args
    w, h = VARIANTES[var][:2]
    page = os.path.join(tmp, f"f{i:03d}.html")
    open(page, "w").write(html(var, i / FPS))
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
                    f"--user-data-dir={tmp}/profil{i % 6}", "--virtual-time-budget=200",
                    f"--window-size={w},{h}", f"--screenshot={tmp}/f{i:03d}.png", "file://" + page],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=60)

if __name__ == "__main__":
    import sys
    for var in (sys.argv[1:] or VARIANTES):
        tmp = os.path.join(OUT, f"_frames-{var}")
        shutil.rmtree(tmp, ignore_errors=True); os.makedirs(tmp)
        n = int(FPS * DUREE)
        with ThreadPoolExecutor(6) as ex:
            list(ex.map(frame, [(var, i, tmp) for i in range(n)]))
        mp4, gif = os.path.join(OUT, var + ".mp4"), os.path.join(OUT, var + ".gif")
        subprocess.run([FFMPEG, "-y", "-loglevel", "error", "-framerate", str(FPS), "-i", f"{tmp}/f%03d.png",
                        "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "18", "-movflags", "+faststart", mp4], check=True)
        gw = 720 if VARIANTES[var][0] > VARIANTES[var][1] else 480
        subprocess.run([FFMPEG, "-y", "-loglevel", "error", "-framerate", str(FPS), "-i", f"{tmp}/f%03d.png", "-vf",
                        f"fps=25,scale={gw}:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=64[p];[b][p]paletteuse=dither=none",
                        "-loop", "0", gif], check=True)
        print("✓", var, n, "images")
