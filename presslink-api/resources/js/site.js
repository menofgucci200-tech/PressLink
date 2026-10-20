// Mouvement du site vitrine (pages publiques) : défilement doux, titres qui
// montent mot par mot, éléments qui entrent en cascade. L'accueil a en plus
// l'arrivée de la marque et le téléphone qui se remplit. Les éléments ciblés
// portent un attribut data-motion ; l'identité de la page ne change pas.
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

gsap.registerPlugin(ScrollTrigger);

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const montrer = () => document.documentElement.classList.add('motion-pret');

if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
    montrer();
} else {
    demarrer();
}

function demarrer() {
    const lenis = new Lenis({ lerp: 0.09 });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
    // Les ancres internes passent aussi par le défilement doux
    $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
        const cible = a.getAttribute('href').length > 1 && $(a.getAttribute('href'));
        if (cible) { e.preventDefault(); lenis.scrollTo(cible, { offset: -80 }); }
    }));

    const progression = document.createElement('div');
    progression.className = 'motion-progression';
    document.body.appendChild(progression);
    gsap.to(progression, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });

    const hero = $('[data-motion="hero"]');
    if (hero) accueil(hero);
    else montrer();

    titres(hero);
    cascades();
    accueilSections();
    boutons();
    gsap.from($$('footer .grid > *'), { y: 30, opacity: 0, duration: 0.9, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: 'footer', start: 'top 95%' } });
    addEventListener('load', () => ScrollTrigger.refresh());
}

/** Découpe un titre en mots masqués, prêts à monter. Les liens et le gras sont conservés. */
function decouper(el) {
    const parcourir = (noeud) => {
        [...noeud.childNodes].forEach((n) => {
            if (n.nodeType === 3 && n.textContent.trim()) {
                const frag = document.createDocumentFragment();
                n.textContent.split(/(\s+)/).forEach((m) => {
                    if (!m.trim()) { frag.appendChild(document.createTextNode(m)); return; }
                    const masque = document.createElement('span');
                    masque.className = 'motion-mot';
                    masque.innerHTML = '<span></span>';
                    masque.firstChild.textContent = m;
                    frag.appendChild(masque);
                });
                n.replaceWith(frag);
            } else if (n.nodeType === 1) {
                parcourir(n);
            }
        });
    };
    parcourir(el);
    return $$('.motion-mot > span', el);
}

/* ---------------------------------------------------------------- accueil : la marque, puis la page */
function accueil(hero) {
    const texte = $('[data-motion="hero-text"]', hero);
    const h1 = $('h1', texte);
    const motsH1 = decouper(h1);
    const autres = [...texte.children].filter((e) => e !== h1);
    const telephone = $('[data-motion="hero-phone"]', hero);
    const cartes = $$('.mock-card', telephone);
    const barres = $$('.mock-card span[style*="flex:1"]', telephone);
    const pastilles = $$('.mock-pill', telephone);
    const notif = $('.float-y', telephone);

    gsap.set(motsH1, { yPercent: 110 });
    gsap.set(autres, { y: 24, opacity: 0 });
    gsap.set(telephone, { y: 140, rotation: 4, opacity: 0 });
    gsap.set(cartes, { y: 16, opacity: 0 });
    gsap.set(barres, { scaleX: 0, transformOrigin: '0 50%' });
    gsap.set(pastilles, { scale: 0 });
    if (notif) { notif.classList.remove('float-y'); gsap.set(notif, { yPercent: 160, opacity: 0 }); gsap.to(notif, { y: -8, duration: 3, yoyo: true, repeat: -1, ease: 'sine.inOut' }); }
    gsap.set('body > header', { y: -70, opacity: 0 });
    montrer();

    // La marque n'apparaît qu'à la première visite de la session.
    let marque = false;
    try { marque = !sessionStorage.getItem('pl-marque'); sessionStorage.setItem('pl-marque', '1'); } catch { marque = true; }
    const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
    if (marque) {
        const ecran = document.createElement('div');
        ecran.className = 'motion-marque';
        ecran.innerHTML = `<div><div class="nom">${[...'Press'].map((c) => `<span>${c}</span>`).join('')}${[...'Link'].map((c) => `<span class="lien">${c}</span>`).join('')}</div>
            <div class="trait"></div><div class="slogan"><span>Le lien entre votre pressing et vos clients</span></div></div>`;
        document.body.appendChild(ecran);
        tl.from($$('.nom span', ecran), { yPercent: 110, duration: 0.9, stagger: 0.045 })
            .from($('.trait', ecran), { scaleX: 0, duration: 0.9, ease: 'power3.inOut' }, 0.25)
            .from($('.slogan span', ecran), { yPercent: 110, duration: 0.8 }, 0.45)
            .to(ecran, { yPercent: -100, duration: 1, ease: 'expo.inOut', onComplete: () => ecran.remove() }, 1.5);
    }
    const t0 = marque ? 2.0 : 0.1;
    tl.to('body > header', { y: 0, opacity: 1, duration: 0.9 }, t0)
        .to(motsH1, { yPercent: 0, duration: 1.1, stagger: 0.045 }, t0 + 0.1)
        .to(autres, { y: 0, opacity: 1, duration: 0.9, stagger: 0.08 }, t0 + 0.05)
        .to(telephone, { y: 0, rotation: 0, opacity: 1, duration: 1.4 }, t0 + 0.2)
        .to(cartes, { y: 0, opacity: 1, duration: 0.7, stagger: 0.15 }, t0 + 0.9)
        .to(barres, { scaleX: 1, duration: 0.5, stagger: 0.08, ease: 'power2.out' }, t0 + 1.1)
        .to(pastilles, { scale: 1, duration: 0.6, stagger: 0.15, ease: 'back.out(3)' }, t0 + 1.3);
    if (notif) {
        tl.to(notif, { yPercent: 0, opacity: 1, duration: 0.9, ease: 'back.out(1.6)' }, t0 + 1.7);
        // la notification repart puis revient, comme une nouvelle commande prête
        gsap.timeline({ repeat: -1, delay: t0 + 7, repeatDelay: 5 })
            .to(notif, { yPercent: 160, opacity: 0, duration: 0.5, ease: 'power2.in' })
            .to(notif, { yPercent: 0, opacity: 1, duration: 0.9, ease: 'back.out(1.6)' }, 1.2);
    }
    gsap.to(texte, { y: -70, opacity: 0.2, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to(telephone, { y: 90, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ---------------------------------------------------------------- titres de toutes les pages */
function titres(hero) {
    $$('main h1, main h2').forEach((h) => {
        if (h.closest('a') || (hero && hero.contains(h)) || h.closest('[data-motion-ignore]')) return;
        const mots = decouper(h);
        gsap.from(mots, { yPercent: 110, duration: 1, stagger: 0.05, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 90%' } });
        const suite = h.nextElementSibling;
        if (suite && suite.tagName === 'P') gsap.from(suite, { y: 24, opacity: 0, duration: 0.9, delay: 0.2, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 90%' } });
    });
}

/* ---------------------------------------------------------------- blocs en cascade (toutes les pages) */
function cascades() {
    $$('[data-motion="cascade"]').forEach((bloc) => {
        gsap.from([...bloc.children], { y: 70, opacity: 0, duration: 1, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: bloc, start: 'top 88%' } });
    });
    $$('main .phone-mock').forEach((p) => {
        if (p.closest('[data-motion="hero"]') || p.closest('[data-motion="install"]')) return;
        gsap.from(p, { y: 80, rotation: -4, opacity: 0, duration: 1.3, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 88%' } });
    });
}

/* ---------------------------------------------------------------- sections de l'accueil */
function accueilSections() {
    const videos = $$('[data-motion="videos"] figure');
    if (videos.length) {
        gsap.from(videos, { y: 90, scale: 0.94, opacity: 0, duration: 1.2, stagger: 0.15, ease: 'expo.out', scrollTrigger: { trigger: videos[0], start: 'top 88%' } });
        videos.forEach((f) => {
            const v = $('video', f);
            f.addEventListener('pointermove', (e) => { const r = f.getBoundingClientRect(); gsap.to(v, { rotationY: ((e.clientX - r.left) / r.width - 0.5) * 6, rotationX: -((e.clientY - r.top) / r.height - 0.5) * 6, transformPerspective: 1000, duration: 0.6, ease: 'power2.out' }); });
            f.addEventListener('pointerleave', () => gsap.to(v, { rotationX: 0, rotationY: 0, duration: 0.8, ease: 'power3.out' }));
        });
    }
    $$('[data-motion="install"] > *').forEach((p, i) => {
        gsap.from(p, { x: i ? 80 : -80, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 88%' } });
        const tel = $('.phone-mock', p);
        if (tel) gsap.from(tel, { y: 80, rotation: i ? 8 : -8, opacity: 0, duration: 1.3, delay: 0.15, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 82%' } });
        gsap.from($$('.install-steps li', p), { x: 40, opacity: 0, duration: 0.8, stagger: 0.12, delay: 0.25, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 82%' } });
    });

    const carteQr = $('[data-motion="qr"]');
    if (carteQr) {
        const qr = $('img', carteQr);
        const balayage = document.createElement('div');
        balayage.className = 'motion-balayage';
        carteQr.appendChild(balayage);
        gsap.timeline({ scrollTrigger: { trigger: carteQr, start: 'top 85%' } })
            .from(carteQr, { rotationY: -70, transformPerspective: 1100, opacity: 0, duration: 1.2, ease: 'expo.out' })
            .fromTo(qr, { clipPath: 'inset(0 0 100% 0)' }, { clipPath: 'inset(0 0 0% 0)', duration: 1.1, ease: 'power2.inOut' }, 0.4)
            .fromTo(balayage, { top: () => qr.offsetTop, opacity: 1 }, { top: () => qr.offsetTop + qr.offsetHeight, duration: 1.1, ease: 'power2.inOut' }, 0.4)
            .to(balayage, { opacity: 0, duration: 0.3 });
    }
    const contact = $('[data-motion="contact"]');
    if (contact) {
        gsap.from([...contact.children].filter((e) => e.tagName !== 'H2'), { y: 30, opacity: 0, duration: 0.9, stagger: 0.1, delay: 0.25, ease: 'expo.out', scrollTrigger: { trigger: contact, start: 'top 85%' } });
        const wa = $('a[href*="wa.me"]', contact);
        if (wa) wa.classList.add('motion-pulse');
    }
    const numero = $('[data-motion="numero"]');
    if (numero) {
        const final = numero.textContent.trim();
        ScrollTrigger.create({ trigger: numero, start: 'top 90%', once: true, onEnter: () => {
            let i = 0;
            const t = setInterval(() => {
                i++;
                numero.textContent = [...final].map((c, k) => (/\d/.test(c) && k >= i ? Math.floor(Math.random() * 10) : c)).join('');
                if (i > final.length) { clearInterval(t); numero.textContent = final; }
            }, 45);
        } });
    }
}

/* ---------------------------------------------------------------- boutons aimantés */
function boutons() {
    if (!matchMedia('(hover: hover)').matches) return;
    $$('main a.rounded-xl, main a.rounded-full').forEach((b) => {
        b.addEventListener('pointermove', (e) => { const r = b.getBoundingClientRect(); gsap.to(b, { x: (e.clientX - r.left - r.width / 2) * 0.18, y: (e.clientY - r.top - r.height / 2) * 0.25, duration: 0.4 }); });
        b.addEventListener('pointerleave', () => gsap.to(b, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1, .45)' }));
    });
}
