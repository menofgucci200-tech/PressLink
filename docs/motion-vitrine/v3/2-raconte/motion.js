// Proposition 2 · Raconté : la page actuelle, identité inchangée ; le défilement raconte.
gsap.registerPlugin(ScrollTrigger);
const lenis = new Lenis({ lerp: 0.085 });
window.lenis = lenis;
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add((t) => lenis.raf(t * 1000));
gsap.ticker.lagSmoothing(0);
document.body.classList.add('motion-ok');

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const [hero, videos, installer, pages, contact] = $$('main > section');
const mobile = matchMedia('(max-width: 767px)').matches;
function decouper(el) {
    el.innerHTML = el.textContent.trim().split(/\s+/).map((m) => `<span class="mot-masque"><span>${m}</span></span>`).join(' ');
    return $$('.mot-masque > span', el);
}
const prog = document.createElement('div');
prog.className = 'progression';
document.body.appendChild(prog);
gsap.to(prog, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });

/* ---------------------------------------------------------------- héros : une commande prend vie au défilement */
const h1 = $('h1', hero), motsH1 = decouper(h1);
const texte = h1.parentElement, accroche = $('p.uppercase', hero);
const reste = [...texte.children].filter((e) => e !== h1 && e !== accroche);
const telephone = $('.phone-mock', hero), mock = $('.mock', hero);
const [carte1] = $$('.mock-card', mock);
const pastille1 = $('.mock-pill', carte1);
const barres1 = $$('span[style*="flex:1"]', carte1);
const notif = $('.float-y', mock);
const etapes = document.createElement('div');
etapes.className = 'fil-etapes';
etapes.innerHTML = '<span><i></i></span><span><i></i></span><span><i></i></span>';
reste[reste.length - 1].after(etapes);
const disque = document.createElement('div');
disque.className = 'disque';
telephone.parentElement.style.position = 'relative';
telephone.parentElement.insertBefore(disque, telephone);
const ETATS = [
    { texte: '● Reçue', fond: 'var(--color-primary-tint)', couleur: 'var(--color-primary)', bord: 'var(--color-border)', barres: 1 },
    { texte: '● En traitement', fond: 'var(--color-warning-tint)', couleur: 'var(--color-warning-text)', bord: 'var(--color-border)', barres: 2 },
    { texte: '● Prête', fond: 'var(--color-success-tint)', couleur: 'var(--color-success-text)', bord: '#a7f3d0', barres: 3 },
];
let etatCourant = -1;
function etat(i) {
    if (i === etatCourant) return;
    etatCourant = i;
    const e = ETATS[i];
    pastille1.textContent = e.texte;
    gsap.fromTo(pastille1, { scale: 0.6 }, { scale: 1, duration: 0.5, ease: 'back.out(3)', background: e.fond, color: e.couleur });
    gsap.to(carte1, { borderColor: e.bord, boxShadow: i === 2 ? '0 .4em 1em rgb(16 185 129 / .18)' : '0 0 0 rgb(0 0 0 / 0)', duration: 0.4 });
    barres1.forEach((b, k) => gsap.to(b, { background: k < e.barres ? (i === 2 ? 'var(--color-success)' : 'var(--color-primary)') : 'var(--color-border)', duration: 0.4 }));
    gsap.to(notif, { yPercent: i === 2 ? 0 : 160, opacity: i === 2 ? 1 : 0, duration: i === 2 ? 0.8 : 0.3, ease: i === 2 ? 'back.out(1.6)' : 'power2.in' });
}
gsap.set(notif, { yPercent: 160, opacity: 0 });
gsap.to(notif, { y: -8, duration: 3, yoyo: true, repeat: -1, ease: 'sine.inOut' });
etat(0);

// arrivée
gsap.set(motsH1, { color: 'var(--color-text-muted)', opacity: 0.35 });
const arrivee = gsap.timeline({ defaults: { ease: 'expo.out' } });
arrivee.from('body > header', { y: -70, opacity: 0, duration: 0.9 })
    .from(accroche, { y: 20, opacity: 0, duration: 0.8 }, 0.15)
    .from(motsH1, { yPercent: 110, duration: 1.1, stagger: 0.04 }, 0.2)
    .from(reste, { y: 24, opacity: 0, duration: 0.9, stagger: 0.1 }, 0.5)
    .from(etapes, { opacity: 0, duration: 0.6 }, 0.9)
    .from(telephone, { y: 160, rotation: 4, opacity: 0, duration: 1.4 }, 0.3)
    .from(disque, { scale: 0, duration: 1.4 }, 0.5);

function placerDisque() {
    const r = telephone.getBoundingClientRect(), p = telephone.parentElement.getBoundingClientRect(), d = r.width * 1.05;
    Object.assign(disque.style, { width: d + 'px', height: d + 'px', left: r.left - p.left + r.width / 2 - d / 2 + 'px', top: r.top - p.top + r.height * 0.32 - d / 2 + 'px' });
}
placerDisque();
addEventListener('resize', placerDisque);

ScrollTrigger.create({
    trigger: hero, pin: true, start: 'top top', end: mobile ? '+=160%' : '+=220%', scrub: true,
    onUpdate: (st) => {
        const p = st.progress;
        // le titre se remplit de mot en mot
        motsH1.forEach((m, i) => {
            const k = gsap.utils.clamp(0, 1, (p * 1.6 - i / motsH1.length) * motsH1.length / 3);
            m.style.opacity = 0.35 + 0.65 * k;
            m.style.color = k > 0.5 ? 'var(--color-text-primary)' : 'var(--color-text-muted)';
        });
        // la commande avance : reçue, en traitement, prête
        etat(p < 0.34 ? 0 : p < 0.67 ? 1 : 2);
        $$('i', etapes).forEach((b, i) => (b.style.transform = `scaleX(${gsap.utils.clamp(0, 1, p * 3 - i)})`));
        gsap.set(telephone, { scale: 1 + p * 0.06, rotation: Math.sin(p * Math.PI) * -2 });
        gsap.set(disque, { scale: 1 + p * 0.35 });
    },
});

/* ---------------------------------------------------------------- titres */
$$('main h2').forEach((h) => {
    if (h.closest('a')) return;
    const mots = decouper(h);
    gsap.from(mots, { yPercent: 110, duration: 1, stagger: 0.05, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 88%' } });
    const suite = h.nextElementSibling;
    if (suite && suite.tagName === 'P') gsap.from(suite, { y: 24, opacity: 0, duration: 0.9, delay: 0.2, ease: 'expo.out', scrollTrigger: { trigger: h, start: 'top 88%' } });
});

/* ---------------------------------------------------------------- vidéos : elles s'ouvrent au défilement */
$$('figure', videos).forEach((f, i) => {
    const v = $('video', f);
    gsap.fromTo(v, { clipPath: 'inset(18% 14% 18% 14% round 24px)', scale: 1.08 }, { clipPath: 'inset(0% 0% 0% 0% round 24px)', scale: 1, ease: 'none', scrollTrigger: { trigger: f, start: 'top 95%', end: 'top 40%', scrub: 0.6 } });
    gsap.from($('figcaption', f), { y: 20, opacity: 0, duration: 0.8, ease: 'expo.out', scrollTrigger: { trigger: f, start: 'top 60%' } });
    gsap.to(f, { y: i ? -40 : 0, ease: 'none', scrollTrigger: { trigger: videos, start: 'top bottom', end: 'bottom top', scrub: true } });
});

/* ---------------------------------------------------------------- installation : les étapes suivent le défilement */
const scenes = $$('.install-demo .scene', installer), lignes = $$('.install-steps li', installer);
const DUREE = 12;
function avancer(temps) {
    // les animations CSS existantes (cycle de 12 s, une étape toutes les 4 s) sont pilotées par le défilement
    [...scenes, ...lignes].forEach((el) => {
        const i = [...el.parentElement.children].filter((c) => c.tagName === el.tagName).indexOf(el);
        el.style.animationDelay = `${-(temps - i * 4 + DUREE) % DUREE}s`;
    });
}
avancer(0.5);
const panneaux = $$('.grid > div', installer).filter((d) => d.querySelector('h3'));
panneaux.forEach((p, i) => gsap.from(p, { y: 80, opacity: 0, duration: 1.1, delay: i * 0.12, ease: 'expo.out', scrollTrigger: { trigger: installer, start: 'top 70%' } }));
if (!mobile) {
    ScrollTrigger.create({ trigger: installer, pin: true, start: 'top top', end: '+=200%', scrub: true, onUpdate: (st) => avancer(0.5 + st.progress * 11.2) });
} else {
    panneaux.forEach((p) => ScrollTrigger.create({ trigger: p, start: 'top 70%', end: 'bottom 30%', scrub: true, onUpdate: (st) => avancer(0.5 + st.progress * 11.2) }));
}

/* ---------------------------------------------------------------- les trois accès : du paquet à la grille */
const acces = $$('a.group', pages);
gsap.fromTo(acces, { x: (i) => (i - 1) * -(acces[0].offsetWidth + 20), y: (i) => i * 14, rotation: (i) => (i - 1) * 5, scale: 0.92 },
    { x: 0, y: 0, rotation: 0, scale: 1, ease: 'power2.out', scrollTrigger: { trigger: pages, start: 'top 85%', end: 'center 55%', scrub: 0.6 } });

/* ---------------------------------------------------------------- QR et contact */
const carteQr = contact.firstElementChild, blocContact = contact.lastElementChild;
gsap.fromTo(carteQr, { scale: 0.7, rotation: -6, opacity: 0.3 }, { scale: 1, rotation: 0, opacity: 1, ease: 'power2.out', scrollTrigger: { trigger: contact, start: 'top 90%', end: 'center 60%', scrub: 0.6 } });
gsap.fromTo($$(':scope > *', blocContact), { x: 80, opacity: 0 }, { x: 0, opacity: 1, stagger: 0.08, ease: 'power2.out', scrollTrigger: { trigger: contact, start: 'top 80%', end: 'center 60%', scrub: 0.6 } });
const wa = $$('a[href*="wa.me"]', blocContact)[0];
if (wa) wa.classList.add('pulse-wa');
gsap.from($$('footer .grid > *'), { y: 30, opacity: 0, duration: 0.9, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: 'footer', start: 'top 92%' } });
addEventListener('load', () => { placerDisque(); ScrollTrigger.refresh(); });
