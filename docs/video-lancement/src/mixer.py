"""Mixage audio : voix off traitée (chaleur, présence, compression douce)
placée selon la timeline, sur la musique déjà « duckée », puis normalisation
à -14 LUFS (réseaux sociaux).

Usage : python mixer.py <timeline.json> <dossier_voix> <musique.wav> <sortie.wav>
"""
import json
import subprocess
import sys


def main(timeline_path: str, dossier_voix: str, musique: str, sortie: str) -> None:
    timeline = json.load(open(timeline_path))
    entrees, filtres, labels = [], [], []
    for i, v in enumerate(timeline['voix']):
        entrees += ['-i', f"{dossier_voix}/{v['cle']}.wav"]
        delai = int(v['debut'] * 1000)
        filtres.append(
            f"[{i}:a]aresample=48000,highpass=f=70,"
            "equalizer=f=190:t=q:w=0.9:g=2.5,"      # chaleur
            "equalizer=f=3200:t=q:w=1.4:g=1.5,"     # présence
            "equalizer=f=7000:t=q:w=1.5:g=-2,"      # sibilantes adoucies
            "acompressor=threshold=-21dB:ratio=2.6:attack=8:release=140:makeup=2.2,"
            f"adelay={delai}|{delai},pan=stereo|c0=c0|c1=c0[v{i}]"
        )
        labels.append(f'[v{i}]')
    n = len(labels)
    entrees += ['-i', musique]
    filtres.append(f"{''.join(labels)}amix=inputs={n}:normalize=0,volume=1.0[voix]")
    filtres.append(f'[{n}:a]highpass=f=32,volume=0.9[mus]')
    filtres.append(
        '[voix][mus]amix=inputs=2:normalize=0:duration=longest,'
        f"atrim=0:{timeline['duree']},"
        'loudnorm=I=-14:TP=-1.5:LRA=9[out]'
    )
    cmd = ['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', *entrees,
           '-filter_complex', ';'.join(filtres), '-map', '[out]', '-ar', '48000', sortie]
    subprocess.run(cmd, check=True)
    print('mix ->', sortie)


if __name__ == '__main__':
    main(*sys.argv[1:5])
