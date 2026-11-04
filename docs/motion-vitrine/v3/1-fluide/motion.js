// Proposition 1 · Fluide : la page actuelle, identité inchangée, mise en mouvement.
gsap.registerPlugin(ScrollTrigger);
const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const lenis = new Lenis({ lerp: 0.09 });
window.lenis = lenis;
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add((t) => lenis.raf(t * 1000));
gsap.ticker.lagSmoothing(0);
document.body.classList.add('motion-ok');

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const sections = $$('main > section');
const [hero, videos, installer, pages, contact] = sections;

/** Découpe un titre en mots masqués, prêts à monter. */
function decouper(el) {
    const mots = el.textContent.trim().split(/\s+/);
    el.innerHTML = mots.map((m) => `<span class="mot-masque"><span>${m}</span></span>`).join(' ');
    return $$('.mot-masque > span', el);
}

/* ---------------------------------------------------------------- arrivée : la marque, puis la page */
const chargeur = document.createElement('div');
chargeur.className = 'chargeur';
chargeur.innerHTML = `<div><div class="marque">${[...'Press'].map((c) => `<span>${c}</span>`).join('')}${[...'Link'].map((c) => `<span style="color:var(--color-primary)">${c}</span>`).join('')}</div>
    <div class="trait"></div><div class="slogan"><span>Le lien entre votre pressing et vos clients</span></div></div>`;
document.body.appendChild(chargeur);
const prog = document.createElement('div');
prog.className = 'progression';
document.body.appendChild(prog);

const motsH1 = decouper($('h1', hero));
const accroche = $('p.uppercase', hero);
const blocTexte = [...$('h1', hero).parentElement.children].filter((e) => e.tagName !== 'H1' && e !== accroche);
const telephone = $('.phone-mock', hero);
const mock = $('.mock', hero);
const cartes = $$('.mock-card', mock);
const barres = $$('.mock-card span[style*="flex:1"]', mock);
const pastilles = $$('.mock-pill', mock);
const notif = $('.float-y', mock);

gsap.set(motsH1, { yPercent: 110 });
gsap.set([accroche, ...blocTexte], { y: 24, opacity: 0 });
gsap.set(telephone, { y: 140, rotation: 4, opacity: 0 });
gsap.set(cartes, { y: 16, opacity: 0 });
gsap.set(barres, { scaleX: 0, transformOrigin: '0 50%' });
gsap.set(pastilles, { scale: 0 });
gsap.set(notif, { yPercent: 160, opacity: 0 });
gsap.to(notif, { y: -8, duration: 3, yoyo: true, repeat: -1, ease: 'sine.inOut' });
gsap.set('body > header', { y: -70, opacity: 0 });

const arrivee = gsap.timeline({ defaults: { ease: 'expo.out' } });
arrivee.from('.chargeur .marque span', { yPercent: 110, duration: 0.9, stagger: 0.045 })
    .from('.chargeur .trait', { scaleX: 0, duration: 0.9, ease: 'power3.inOut' }, 0.25)
    .from('.chargeur .slogan span', { yPercent: 110, duration: 0.8 }, 0.45)
    .to(chargeur, { yPercent: -100, duration: 1.0, ease: 'expo.inOut' }, 1.5)
    .to('body > header', { y: 0, opacity: 1, duration: 0.9 }, 2.0)
    .to(accroche, { y: 0, opacity: 1, duration: 0.8 }, 2.05)
    .to(motsH1, { yPercent: 0, duration: 1.1, stagger: 0.045 }, 2.1)
    .to(blocTexte, { y: 0, opacity: 1, duration: 0.9, stagger: 0.1 }, 2.5)
    .to(telephone, { y: 0, rotation: 0, opacity: 1, duration: 1.4 }, 2.2)
    .to(cartes, { y: 0, opacity: 1, duration: 0.7, stagger: 0.15 }, 2.9)
    .to(barres, { scaleX: 1, duration: 0.5, stagger: 0.08, ease: 'power2.out' }, 3.1)
    .to(pastilles, { scale: 1, duration: 0.6, stagger: 0.15, ease: 'back.out(3)' }, 3.3)
    .to(notif, { yPercent: 0, opacity: 1, duration: 0.9, ease: 'back.out(1.6)' }, 3.7)
    .add(() => chargeur.remove());
if (reduit) arrivee.progress(1);

// La notification repart puis revient, comme une nouvelle commande prête
gsap.timeline({ repeat: -1, delay: 9, repeatDelay: 5 })
    .to(notif, { yPercent: 160, opacity: 0, duration: 0.5, ease: 'power2.in' })
    .to(notif, { yPercent: 0, opacity: 1, duration: 0.9, ease: 'back.out(1.6)' }, 1.2);

// Le héros s'efface en douceur au défilement
gsap.to($('h1', hero).parentElement, { y: -70, opacity: 0.2, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
gsap.to(telephone, { y: 90, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
gsap.to(prog, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });

/* ---------------------------------------------------------------- titres de section : mot par mot */
$$('main h2').forEach((h) => {
    if (h.closest('a')) return;
    const mots = decouper(h);
    gsap.from(mots, { yPercent: 110, duration: 1, stagger: 0.05, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 88%' } });
    const suite = h.nextElementSibling;
    if (suite && suite.tagName === 'P') gsap.from(suite, { y: 24, opacity: 0, duration: 0.9, delay: 0.2, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 88%' } });
});

/* ---------------------------------------------------------------- vidéos */
const figures = $$('figure', videos);
gsap.from(figures, { y: 90, scale: 0.94, opacity: 0, duration: 1.2, stagger: 0.15, ease: 'expo.out', scrollTrigger: { trigger: figures[0], start: 'top 85%' } });
figures.forEach((f) => {
    const v = $('video', f);
    gsap.set(f, { transformPerspective: 1000 });
    f.addEventListener('pointermove', (e) => { const r = f.getBoundingClientRect(); gsap.to(v, { rotationY: ((e.clientX - r.left) / r.width - 0.5) * 6, rotationX: -((e.clientY - r.top) / r.height - 0.5) * 6, duration: 0.6, ease: 'power2.out', transformPerspective: 1000 }); });
    f.addEventListener('pointerleave', () => gsap.to(v, { rotationX: 0, rotationY: 0, duration: 0.8, ease: 'power3.out' }));
});
gsap.from($(':scope > a', videos), { x: -20, opacity: 0, duration: 0.8, ease: 'expo.out', scrollTrigger: { trigger: $(':scope > a', videos), start: 'top 95%' } });

/* ---------------------------------------------------------------- installation */
const panneaux = $$('.grid > div', installer).filter((d) => d.querySelector('h3'));
panneaux.forEach((p, i) => {
    gsap.from(p, { x: i ? 80 : -80, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 85%' } });
    gsap.from($('.phone-mock', p), { y: 80, rotation: i ? 8 : -8, opacity: 0, duration: 1.3, delay: 0.15, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 80%' } });
    gsap.from($$('.install-steps li', p), { x: 40, opacity: 0, duration: 0.8, stagger: 0.12, delay: 0.25, ease: 'expo.out', scrollTrigger: { trigger: p, start: 'top 80%' } });
});

/* ---------------------------------------------------------------- les trois accès */
const acces = $$('a.group', pages);
gsap.from(acces, { y: 70, opacity: 0, duration: 1, stagger: 0.12, ease: 'expo.out', scrollTrigger: { trigger: acces[0], start: 'top 88%' } });

/* ---------------------------------------------------------------- QR et contact */
const carteQr = contact.firstElementChild;
const qr = $('img', carteQr);
carteQr.style.position = 'relative';
const balayage = document.createElement('div');
balayage.className = 'qr-balayage';
carteQr.appendChild(balayage);
const tlQr = gsap.timeline({ scrollTrigger: { trigger: carteQr, start: 'top 82%' } });
tlQr.from(carteQr, { rotationY: -70, transformPerspective: 1100, opacity: 0, duration: 1.2, ease: 'expo.out' })
    .fromTo(qr, { clipPath: 'inset(0 0 100% 0)' }, { clipPath: 'inset(0 0 0% 0)', duration: 1.1, ease: 'power2.inOut' }, 0.4)
    .fromTo(balayage, { top: qr.offsetTop, opacity: 1 }, { top: () => qr.offsetTop + qr.offsetHeight, duration: 1.1, ease: 'power2.inOut' }, 0.4)
    .to(balayage, { opacity: 0, duration: 0.3 });
const blocContact = contact.lastElementChild;
gsap.from($$(':scope > *', blocContact).filter((e) => e.tagName !== 'H2'), { y: 30, opacity: 0, duration: 0.9, stagger: 0.1, delay: 0.25, ease: 'expo.out', scrollTrigger: { trigger: blocContact, start: 'top 82%' } });
// le numéro se compose chiffre par chiffre
const numero = $$('p', blocContact).find((p) => /\d{2} \d{2}/.test(p.textContent));
if (numero) {
    const final = numero.textContent.trim();
    ScrollTrigger.create({ trigger: numero, start: 'top 85%', once: true, onEnter: () => {
        let i = 0;
        const t = setInterval(() => {
            i++;
            numero.textContent = [...final].map((c, k) => (/\d/.test(c) && k >= i ? Math.floor(Math.random() * 10) : c)).join('');
            if (i > final.length) { clearInterval(t); numero.textContent = final; }
        }, 45);
    } });
}
const wa = $$('a[href*="wa.me"]', blocContact)[0];
if (wa) wa.classList.add('pulse-wa');

/* ---------------------------------------------------------------- boutons et pied de page */
$$('main a.rounded-xl, main a.rounded-full').forEach((b) => {
    b.addEventListener('pointermove', (e) => { const r = b.getBoundingClientRect(); gsap.to(b, { x: (e.clientX - r.left - r.width / 2) * 0.18, y: (e.clientY - r.top - r.height / 2) * 0.25, duration: 0.4 }); });
    b.addEventListener('pointerleave', () => gsap.to(b, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1, .45)' }));
});
gsap.from($$('footer .grid > *'), { y: 30, opacity: 0, duration: 0.9, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: 'footer', start: 'top 92%' } });
addEventListener('load', () => ScrollTrigger.refresh());
