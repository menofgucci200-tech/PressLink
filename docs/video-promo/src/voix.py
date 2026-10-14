"""Voix off de la vidéo promo PressLink (Kokoro-82M, voix française ff_siwis).

Usage : python voix.py <dossier_kokoro> <dossier_sortie>
Le dossier Kokoro contient kokoro-q8.onnx et voices.bin (npz des voix).
Chaque réplique est générée séparément : sa durée pilote le montage.
"""
import json
import re
import sys

import numpy as np
import soundfile as sf
from kokoro_onnx import Kokoro

# Prononciation « à l'ivoirienne » des mots que espeak lit en anglais.
PHONEMES = {
    'PressLink': 'pʁɛslˈiŋk',
    'pressings': 'pʁɛsˈiŋ',
    'pressing': 'pʁɛsˈiŋ',
    'rien': 'ʁjˈɛ̃',
}

REPLIQUES = [
    ('depot', "Au pressing, on dépose ses vêtements… et on attend, sans savoir."),
    ('lien', "Avec PressLink, le pressing et ses clients restent liés."),
    ('suivi', "Suivez vos vêtements en temps réel, depuis votre téléphone."),
    ('prete', "Dès qu'ils sont prêts, vous êtes prévenu. Fini les déplacements pour rien."),
    ('pressings', "Et pour les pressings : commandes, clients, équipe… tout est simple, et vos clients sont informés automatiquement."),
    ('signature', "PressLink. Le lien entre votre pressing et vos clients."),
    ('cta', "Gratuit pour les clients. Rendez-vous sur PressLink point org."),
]

VOIX = 'ff_siwis'
VITESSE = 0.9


def phonemiser(kokoro: Kokoro, texte: str) -> str:
    motif = re.compile(r'\b(' + '|'.join(sorted(PHONEMES, key=len, reverse=True)) + r')\b')
    morceaux, position = [], 0
    for m in motif.finditer(texte):
        morceaux.append(kokoro.tokenizer.phonemize(texte[position:m.start()], 'fr-fr') if m.start() > position else '')
        morceaux.append(' ' + PHONEMES[m.group(1)] + ' ')
        position = m.end()
    morceaux.append(kokoro.tokenizer.phonemize(texte[position:], 'fr-fr'))
    phonemes = ''.join(morceaux)
    phonemes = re.sub(r'\([a-z-]+\)', '', phonemes)  # drapeaux de langue d'espeak
    return re.sub(r'\s+', ' ', phonemes).strip()


def main(dossier_kokoro: str, sortie: str) -> None:
    kokoro = Kokoro(f'{dossier_kokoro}/kokoro-q8.onnx', f'{dossier_kokoro}/voices.bin')
    index = []
    for cle, texte in REPLIQUES:
        phonemes = phonemiser(kokoro, texte)
        audio, sr = kokoro.create(phonemes, voice=VOIX, speed=VITESSE, is_phonemes=True)
        audio = np.asarray(audio, dtype=np.float32)
        sf.write(f'{sortie}/{cle}.wav', audio, sr)
        index.append({'cle': cle, 'texte': texte, 'phonemes': phonemes, 'duree': round(len(audio) / sr, 3)})
        print(f'{cle:10} {len(audio) / sr:5.2f}s  {phonemes}')
    with open(f'{sortie}/repliques.json', 'w') as f:
        json.dump(index, f, ensure_ascii=False, indent=2)
    print('Total parole :', round(sum(r['duree'] for r in index), 2), 's')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
