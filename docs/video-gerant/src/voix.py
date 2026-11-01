"""Voix off de la vidéo « Gérant de pressing : du cahier au digital » (Kokoro-82M, voix ff_siwis).

Usage : python voix.py <dossier_kokoro> <dossier_sortie>
Le dossier Kokoro contient kokoro-q8.onnx et voices.bin (npz des voix).

Chaque phrase est une réplique séparée : sa durée mesurée place exactement
l'image qui l'illustre (voir timeline.json), ce qui garantit la synchro.
Le débit est un peu ralenti pour une articulation nette, et les mots que
espeak lit mal (anglais, marques) ont une prononciation imposée.
"""
import json
import re
import sys

import numpy as np
import soundfile as sf
from kokoro_onnx import Kokoro

# Prononciation imposée des mots que espeak lit mal ou à l'anglaise.
PHONEMES = {
    'PressLink': 'pʁɛslˈiŋk',
    'pressings': 'pʁɛsˈiŋ',
    'pressing': 'pʁɛsˈiŋ',
    'WhatsApp': 'wˈatsap',
    'Wave': 'wˈɛv',
    'digital': 'diʒitˈal',
}

REPLIQUES = [
    ('a1', "Vous êtes gérant de pressing ?"),
    ('a2', "Et vous notez encore les commandes dans un cahier ?"),
    ('b1', "Un cahier qui se perd, ou qui se mouille."),
    ('b2', "Des tickets griffonnés, que personne n'arrive à relire."),
    ('b3', "Des clients qui appellent toute la journée : c'est prêt ?"),
    ('b4', "Et le soir, une caisse qui ne tombe jamais juste."),
    ('c1', "Grâce au digital, nous avons une solution pour vous."),
    ('c2', "PressLink : le logiciel de gestion de pressing."),
    ('d1', "Chaque dépôt s'enregistre en quelques secondes, et se retrouve en un clic."),
    ('d2', "Le reçu est clair, imprimé ou envoyé au client."),
    ('d3', "D'un coup d'œil, vous voyez les commandes en cours, prêtes, ou en retard."),
    ('d4', "Et le soir, la caisse se clôture sans écart."),
    ('e1', "Vos clients, eux, suivent leur commande sur l'application, et sont prévenus quand c'est prêt."),
    ('f1', "PressLink, c'est gratuit tout le mois de novembre."),
    ('f2', "Rangez le cahier : passez au digital, dès aujourd'hui !"),
]

VOIX = 'ff_siwis'
VITESSE = 0.88


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
    phonemes = re.sub(r'\s+([,.:;!?…])', r'\1', phonemes)  # pas d'espace avant la ponctuation
    return re.sub(r'\s+', ' ', phonemes).strip()


def rogner(audio: np.ndarray, sr: int) -> np.ndarray:
    """Retire les silences de début et de fin (la timeline gère les respirations)."""
    seuil = 0.01 * np.max(np.abs(audio))
    actifs = np.where(np.abs(audio) > seuil)[0]
    if actifs.size == 0:
        return audio
    debut = max(0, actifs[0] - int(0.02 * sr))
    fin = min(len(audio), actifs[-1] + int(0.08 * sr))
    return audio[debut:fin]


def main(dossier_kokoro: str, sortie: str) -> None:
    kokoro = Kokoro(f'{dossier_kokoro}/kokoro-q8.onnx', f'{dossier_kokoro}/voices.bin')
    index = []
    for cle, texte in REPLIQUES:
        phonemes = phonemiser(kokoro, texte)
        audio, sr = kokoro.create(phonemes, voice=VOIX, speed=VITESSE, is_phonemes=True)
        audio = rogner(np.asarray(audio, dtype=np.float32), sr)
        sf.write(f'{sortie}/{cle}.wav', audio, sr)
        index.append({'cle': cle, 'texte': texte, 'phonemes': phonemes, 'duree': round(len(audio) / sr, 3)})
        print(f'{cle:4} {len(audio) / sr:5.2f}s  {phonemes}')
    with open(f'{sortie}/repliques.json', 'w') as f:
        json.dump(index, f, ensure_ascii=False, indent=2)
    print('Total parole :', round(sum(r['duree'] for r in index), 2), 's')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
