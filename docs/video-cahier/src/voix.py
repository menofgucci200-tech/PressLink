"""Voix off de la vidéo « Rangez le cahier » PressLink (Kokoro-82M, voix française ff_siwis).

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
    'WhatsApp': 'wˈatsap',
    'Wave': 'wˈɛv',
}

REPLIQUES = [
    ('cahier', "Le cahier qui se perd, les tickets illisibles, les clients qui appellent pour savoir si c'est prêt…"),
    ('temps', "Il est temps de ranger le cahier. Passez à PressLink !"),
    ('client', "Une nouvelle commande ? On tape le nom ou le numéro du client."),
    ('pieces', "On touche les pièces : trois chemises, deux pantalons, un grand boubou. Le total se calcule tout seul."),
    ('paiement', "Acompte par Wave ou Mobile Money : on valide, et le reçu est prêt à imprimer ou à envoyer."),
    ('suivi', "Ensuite, chaque commande se suit en un clic : en traitement, puis prête. Et le client est prévenu automatiquement."),
    ('cta', "PressLink, c'est gratuit tout le mois de novembre. Rendez-vous dans votre espace pressing !"),
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
