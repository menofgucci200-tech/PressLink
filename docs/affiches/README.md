# Affiches de lancement

Quatre affiches A3 portrait (297 × 420 mm), chacune en PNG (≈ 290 dpi) et en PDF :

| Fichier | Cible | Message |
|---|---|---|
| `affiche-1-prete` | Clients | « Votre linge est prêt ? PressLink vous prévient. » |
| `affiche-2-suivi` | Clients | « Fini les allers-retours au pressing. » — suivi en temps réel |
| `affiche-3-lancement` | Clients | « Votre pressing dans votre poche. » — 3 étapes + grand QR code |
| `affiche-4-pressings` | Pressings | « Vos clients prévenus, automatiquement. » — démo via WhatsApp |

Les QR codes des affiches 1 à 3 ouvrent `https://presslink.org/app/`, celui de l'affiche 4 ouvre WhatsApp (`wa.me/2250718002293`).

## Modifier une affiche

Les sources sont dans `src/` (HTML + `style.css`). Les écrans de téléphone sont du HTML placé sous des photos dont l'écran a été rendu transparent. Pour régénérer :

```bash
node docs/affiches/render.cjs            # toutes les affiches
node docs/affiches/render.cjs suivi      # seulement celles dont le nom contient « suivi »
```

Nécessite Playwright + Chromium et les polices Inter / Inter Display installées.
