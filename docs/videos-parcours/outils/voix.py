"""Voix off et dialogues des maquettes motion PressLink (Kokoro-82M).

Usage : python voix.py <dossier_kokoro> <dossier_maquette> <dossier_sortie>

Lit <dossier_maquette>/script.json :
  {"repliques": [{"cle": "b1", "texte": "...", "voix": "ff_siwis",
                  "vitesse": 1.0, "hauteur": 0, "effet": null}]}
- hauteur : décalage en demi-tons (personnage cartoon, autre personnage) ;
- effet : "telephone" (bande étroite) ou null.
Écrit <cle>.wav (48 kHz) et repliques.json : durée et enveloppe de volume
(30 valeurs par seconde) qui anime la bouche des personnages.
"""
import json
import os
import re
import subprocess
import sys

import espeakng_loader
import numpy as np
import soundfile as sf
from kokoro_onnx import Kokoro
from kokoro_onnx.config import EspeakConfig

# Prononciation des mots que espeak lit à l'anglaise.
PHONEMES = {
    'PressLink': 'pʁɛslˈiŋk',
    'pressings': 'pʁɛsˈiŋ',
    'pressing': 'pʁɛsˈiŋ',
    'WhatsApp': 'wˈatsap',
    'Cocody': 'kɔkɔdˈi',
}

# Les données d'espeak doivent être sur un chemin court (limite de la bibliothèque).
ESPEAK_DATA = os.path.expanduser('~/.cache/pl-espeak/espeak-ng-data')


def phonemiser(kokoro: Kokoro, texte: str) -> str:
    motif = re.compile(r'\b(' + '|'.join(sorted(PHONEMES, key=len, reverse=True)) + r')\b')
    morceaux, position = [], 0
    for m in motif.finditer(texte):
        if m.start() > position:
            morceaux.append(kokoro.tokenizer.phonemize(texte[position:m.start()], 'fr-fr'))
        morceaux.append(' ' + PHONEMES[m.group(1)] + ' ')
        position = m.end()
    morceaux.append(kokoro.tokenizer.phonemize(texte[position:], 'fr-fr'))
    phonemes = re.sub(r'\([a-z-]+\)', '', ''.join(morceaux))
    return re.sub(r'\s+', ' ', phonemes).strip()


def traiter(brut: str, sortie: str, sr: int, hauteur: float, effet: str | None) -> None:
    filtres = []
    if hauteur:
        ratio = 2 ** (hauteur / 12)
        filtres += [f'asetrate={int(sr * ratio)}', f'aresample={sr}', f'atempo={1 / ratio:.5f}']
    if effet == 'telephone':
        filtres += ['highpass=f=420', 'lowpass=f=3200', 'acompressor=threshold=-20dB:ratio=4', 'volume=1.6']
    filtres += ['aresample=48000']
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-i', brut,
                    '-af', ','.join(filtres), sortie], check=True)


def enveloppe(audio: np.ndarray, sr: int, fps: int = 30) -> list[float]:
    pas = sr // fps
    rms = [float(np.sqrt(np.mean(audio[i:i + pas] ** 2))) for i in range(0, len(audio), pas)]
    haut = max(1e-6, np.percentile(rms, 95))
    return [round(min(1.0, r / haut), 3) for r in rms]


def main(dossier_kokoro: str, dossier_maquette: str, sortie: str) -> None:
    os.makedirs(sortie, exist_ok=True)
    kokoro = Kokoro(f'{dossier_kokoro}/kokoro-q8.onnx', f'{dossier_kokoro}/voices.bin',
                    espeak_config=EspeakConfig(espeakng_loader.get_library_path(), ESPEAK_DATA))
    script = json.load(open(f'{dossier_maquette}/script.json'))
    index = []
    for r in script['repliques']:
        phonemes = phonemiser(kokoro, r['texte'])
        audio, sr = kokoro.create(phonemes, voice=r.get('voix', 'ff_siwis'), speed=r.get('vitesse', 1.0), is_phonemes=True)
        brut = f"{sortie}/{r['cle']}.brut.wav"
        sf.write(brut, np.asarray(audio, dtype=np.float32), sr)
        traiter(brut, f"{sortie}/{r['cle']}.wav", sr, r.get('hauteur', 0), r.get('effet'))
        os.remove(brut)
        final, sr2 = sf.read(f"{sortie}/{r['cle']}.wav")
        index.append({'cle': r['cle'], 'texte': r['texte'], 'qui': r.get('qui', ''),
                      'duree': round(len(final) / sr2, 3), 'env': enveloppe(final, sr2)})
        print(f"{r['cle']:6} {len(final) / sr2:5.2f}s  {r['texte']}")
    json.dump(index, open(f'{sortie}/repliques.json', 'w'), ensure_ascii=False)


if __name__ == '__main__':
    main(*sys.argv[1:4])
