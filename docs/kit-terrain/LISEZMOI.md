# Kit terrain PressLink

Les supports à laisser dans les pressings démarchés, à la place d'un simple numéro de téléphone.
Vue d'ensemble : `apercu-kit.png`.

Coordonnées utilisées partout : **07 18 00 22 93** (appel et WhatsApp, même numéro, comme indiqué dans
`presslink-api/config/presslink.php`), **contact@presslink.org**, **presslink.org**, appli client **presslink.org/app**.

## Quel fichier sert à quoi

| Support | Pour qui | Fichier à envoyer à l'imprimeur | Aperçu |
|---|---|---|---|
| Carte de visite 85 × 55 mm, recto + verso | Le gérant démarché | `1-carte-visite/carte-visite-imprimerie.pdf` (page 1 = recto, page 2 = verso) | `carte-visite-recto.png`, `carte-visite-verso.png` |
| Affiche A4 | Les clients du pressing (à coller au comptoir ou en vitrine) | `3-affiche-clients/affiche-clients-A4-imprimerie.pdf` | `affiche-clients-A4.png` |
| Affiche A3 | Pareil, pour un mur ou une vitrine | `3-affiche-clients/affiche-clients-A3-imprimerie.pdf` | `affiche-clients-A3.png` |
| Flyer A5 recto | Le gérant démarché | `4-flyer-gerants/flyer-gerants-A5-imprimerie.pdf` | `flyer-gerants-A5.png` |
| Carte numérique, format téléphone 1080 × 1920 | À envoyer sur WhatsApp après la visite | — | `2-carte-numerique/carte-numerique-telephone.png` |
| Carte numérique, format carré 1080 × 1080 | WhatsApp, statut, réseaux sociaux | — | `2-carte-numerique/carte-numerique-carree.png` |
| Contact à enregistrer | À envoyer avec la carte numérique : le gérant touche le fichier et enregistre PressLink dans ses contacts | — | `2-carte-numerique/presslink.vcf` |
| QR codes seuls (SVG vectoriel + PNG) | Pour d'autres supports | — | `qr-codes/` |

Chaque PDF existe en deux versions :

- **`…-imprimerie.pdf`** : fond perdu de 3 mm **et** traits de coupe (page = format fini + 10 mm de chaque côté).
  C'est le fichier à donner à un imprimeur classique.
- **`…-fond-perdu.pdf`** : fond perdu de 3 mm **sans** traits de coupe (page = format fini + 3 mm de chaque côté).
  À utiliser pour les imprimeurs en ligne ou les ateliers qui demandent « un PDF avec 3 mm de débord, sans repères ».

Les PDF sont vectoriels (textes, logo et QR codes nets à toutes les tailles), les polices sont incorporées,
et les boîtes TrimBox (format fini) et BleedBox (fond perdu) sont renseignées.

Les QR codes :

- carte de visite, flyer et cartes numériques → **https://presslink.org** (le site : tester ou créer son compte) ;
- affiche clients → **https://presslink.org/app** (installer l'appli client).

## Papier conseillé

| Support | Papier | Finition |
|---|---|---|
| Carte de visite | Couché mat 350 g/m² (ou 400 g/m² pour plus de tenue) | Recto-verso quadri. Pelliculage mat recto-verso conseillé (le recto est un grand aplat foncé : le pelliculage évite les traces de doigts et les rayures). |
| Affiche A4 / A3 | Couché satiné 170 g/m² | Recto seul. Pour une affiche collée près d'une vitrine ou exposée au soleil : plastification ou papier synthétique, sinon le bleu passe vite. |
| Flyer A5 | Couché satiné 135 à 170 g/m² | Recto seul (verso blanc). |

## Ce qu'il faut dire et demander à l'imprimeur

1. **Format fini et fond perdu** : « Format fini 85 × 55 mm (ou A4, A3, A5), fond perdu 3 mm inclus, traits de coupe dans le PDF. »
2. **Couleurs** : les fichiers sont en **RVB**. Demander à l'imprimeur de les convertir en CMJN avec son profil
   habituel (par exemple ISO Coated v2 / Fogra39 pour du couché). Équivalences indicatives de la charte :
   Bleu PressLink #2563EB ≈ C84 M58 J0 N8, Nuit #0F172A ≈ C64 M45 J0 N84, Encre #0B1530 ≈ C77 M56 J0 N81,
   Bleu clair #60A5FA ≈ C62 M34 J0 N2, Vert #16A34A ≈ C87 M0 J55 N36.
   Le bleu vif d'écran devient un peu plus terne en CMJN : c'est normal.
3. **Demander un BAT** (bon à tirer), si possible imprimé sur le vrai papier, pour vérifier :
   - que le bleu et le fond bleu nuit ne virent pas au violet ou au gris ;
   - que les grands aplats foncés (recto de la carte, haut du flyer, bas de l'affiche) sont bien couvrants et sans bandes ;
   - **que chaque QR code se scanne** avec un téléphone (appareil photo) sur le BAT.
4. **Ne rien redimensionner** : la carte doit rester à 85 × 55 mm (le QR code y mesure 23,5 mm, zone de silence en plus).
   Ne pas imprimer l'affiche A3 à partir du PDF A4 agrandi : utiliser le PDF A3.
5. **Carte de visite, verso** : la bande bleue du bas fait 2,2 mm dans le format fini, plus 3 mm de fond perdu.
   Avec la tolérance de coupe habituelle (± 0,5 à 1 mm), sa hauteur peut varier légèrement d'une carte à l'autre : c'est voulu.
   Recto et verso sont dans le même sens (paysage), à imprimer en « retournement sur le grand côté ».
6. Le texte gris sous chaque page du PDF imprimerie (nom du support, format) est hors format : il part à la coupe.

## Marges de sécurité respectées

- Carte de visite : tous les textes et le QR code sont à 5 mm au moins du bord de coupe (le minimum courant est de 3 mm).
- Affiche A4 : 14 à 16 mm ; A3 : les mêmes proportions (environ 20 à 23 mm).
- Flyer A5 : 9 à 11 mm.

Tailles des QR codes (code seul, sans la zone de silence) : 23,5 mm sur la carte, 21 mm sur le flyer,
69 mm sur l'affiche A4, 98 mm sur l'affiche A3. Niveau de correction H, modules bleu nuit sur blanc,
zone de silence de 4 modules, symbole PressLink au centre (10 à 11 % de la surface).

## Contenu : d'où viennent les textes

Tout ce qui est écrit sur les supports reprend ce qui existe dans l'application et sur le site
(`presslink-api/resources/views/landing.blade.php`, `config/presslink.php`, `app/Enums/OrderStatus.php`,
`app/Notifications/OrderReadyNotification.php`, l'appli client `presslink-app`) :
slogan « Le lien entre votre pressing et vos clients », statuts Reçue / En traitement / Prête / Récupérée,
notification « Votre commande est prête ! », moyens de paiement, caisse du jour, rôles de l'équipe,
« Tester » (presslink.org/tester, pressing fictif, sans inscription), « Inscription » (presslink.org/inscription,
2 minutes, sans engagement, l'équipe rappelle), appli client gratuite installée depuis presslink.org/app,
connexion avec le numéro de téléphone donné au pressing.

Volontairement absents : prix, offre de lancement et dates (ils changent), noms de personnes.
Le numéro de commande « #0427 » sur le flyer est un exemple.

## Régénérer les fichiers

Les sources sont dans `_build/` :

- `build.cjs` : mise en page HTML/CSS, QR codes, vCard, rendu PDF et PNG par Chrome sans interface ;
- `boites-pdf.swift` : remet la taille de page exacte (Chrome l'arrondit au centième de pouce) et ajoute TrimBox et BleedBox ;
- `planche.cjs` : planche d'aperçu `apercu-kit.png` ;
- `verifier-qr.cjs` : décode les QR codes des PNG (net, réduit de moitié, flou) ;
- `pdf2png.swift` : rastérise un PDF en PNG pour le contrôle (`swift pdf2png.swift fichier.pdf dossier 300`).

```sh
cd docs/kit-terrain/_build
export NODE_PATH=<node_modules contenant qrcode>:<node_modules contenant playwright-core>
node build.cjs              # tout ; ou : node build.cjs carte-visite affiche-clients-a4 flyer-gerants numerique
node planche.cjs
node verifier-qr.cjs ../*/*.png
```

Les supports imprimés sont rendus avec les instances statiques des polices Google Fonts (Bricolage Grotesque,
Plus Jakarta Sans), pour des polices TrueType propres dans les PDF. Les images numériques utilisent la police
variable, comme le site.
