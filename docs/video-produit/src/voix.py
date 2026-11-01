"""Voix off de la vidéo « C'est quoi PressLink ? » (Kokoro-82M, voix française ff_siwis).

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
    ('question', "C'est quoi, PressLink ?"),
    ('definition', "PressLink, c'est le logiciel de gestion de pressing pensé pour la Côte d'Ivoire : un espace pour le pressing, et une application pour ses clients."),
    ('commandes', "Au comptoir, chaque dépôt s'enregistre en quelques secondes, avec un reçu à imprimer ou à envoyer."),
    ('suivi', "Le tableau de bord montre où en est chaque commande : reçue, en traitement, prête."),
    ('caisse', "La caisse du jour réunit espèces, Wave et Mobile Money, et se clôture sans écart."),
    ('gestion', "Vos clients, vos tarifs, votre équipe… et même plusieurs pressings, au même endroit."),
    ('appli', "Vos clients, eux, se connectent avec leur numéro : ils suivent leurs vêtements, et sont prévenus dès que c'est prêt."),
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
