@props(['current', 'done' => []])
@php
    // Le fil des 3 étapes de l'accueil : Tester (facultatif), Mon pressing, Ma formule.
    $steps = [
        'tester' => ['Tester', route('sandbox.start')],
        'creer' => ['Mon pressing', route('signup')],
        'formules' => ['Ma formule', route('site.pricing')],
    ];
@endphp
<div class="pc-rail">
    <a href="{{ route('home') }}" class="pc-retour" aria-label="Revenir aux 3 étapes">←<span class="pc-retour-t" aria-hidden="true">&nbsp;Les 3 étapes</span></a>
    <ol>
        @foreach ($steps as $key => [$name, $url])
            @php $isDone = in_array($key, $done, true) && $key !== $current; @endphp
            <li @class(['ici' => $key === $current, 'fait' => $isDone])>
                <a href="{{ $url }}" class="pc-pas" aria-label="Étape {{ $loop->iteration }} : {{ $name }}" @if ($key === $current) aria-current="step" @endif>
                    <i aria-hidden="true">{{ $isDone ? '✓' : $loop->iteration }}</i><span class="t" aria-hidden="true">{{ $name }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</div>
