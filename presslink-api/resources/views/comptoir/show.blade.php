<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Comptoir — PressLink</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <x-theme-script />
    <x-installable-app />
    @vite(['resources/css/app.css', 'resources/js/comptoir.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
@php
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $chip = 'inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border text-sm font-semibold transition-colors';
@endphp
<body class="bg-(--color-bg) text-(--color-text-primary) min-h-screen">
<div x-data="comptoir({{ $pressing->id }}, {{ $user->id }}, @js($icons), @js($colors))" x-cloak class="min-h-screen flex flex-col">

    {{-- Barre du haut : état de la connexion et des envois --}}
    <header class="sticky top-0 z-20 bg-(--color-surface) border-b border-(--color-border)">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
            <div class="font-display text-xl font-bold tracking-tight">Press<span class="text-(--color-primary)">Link</span></div>
            <div class="hidden sm:block text-sm text-(--color-text-secondary) truncate" x-text="donnees?.pressing.name"></div>
            <div class="flex-1"></div>

            <span x-show="!enLigne" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-(--color-warning-tint) text-(--color-warning-text) text-xs font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-(--color-warning)"></span> Hors connexion
            </span>
            <span x-show="enLigne" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-(--color-success-tint) text-(--color-success-text) text-xs font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-(--color-success)"></span> En ligne
            </span>
            <span x-show="file.length > 0" class="inline-flex items-center h-8 px-3 rounded-full bg-(--color-info-tint) text-(--color-info) text-xs font-semibold"
                  x-text="`${file.length} en attente d'envoi`"></span>
            <button type="button" x-show="enLigne && file.length > 0" @click="synchroniser()" :disabled="envoiEnCours"
                    class="h-8 px-3 rounded-lg border border-(--color-border) text-xs font-semibold hover:border-(--color-primary) disabled:opacity-60">
                <span x-text="envoiEnCours ? 'Envoi…' : 'Envoyer maintenant'"></span>
            </button>
            <a href="{{ route('dashboard') }}" x-show="enLigne" class="hidden md:inline-flex h-8 items-center px-3 rounded-lg bg-(--color-primary) text-white text-xs font-semibold hover:bg-(--color-primary-600)">Tableau de bord</a>
        </div>
    </header>

    {{-- Pas encore de copie sur ce PC --}}
    <template x-if="!donnees">
        <main class="flex-1 grid place-items-center p-6">
            <div class="max-w-md text-center bg-(--color-surface) border border-(--color-border) rounded-2xl p-8">
                <h1 class="font-display text-xl font-bold mb-2">Comptoir pas encore prêt sur ce PC</h1>
                <p class="text-sm text-(--color-text-secondary)">Ouvrez PressLink une fois avec Internet sur ce PC : il gardera alors une copie de vos clients, de vos prix et de vos commandes pour travailler sans connexion.</p>
            </div>
        </main>
    </template>

    <template x-if="donnees">
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 py-5">
        <div x-show="!enLigne" class="mb-4 rounded-xl bg-(--color-warning-tint) text-(--color-warning-text) text-sm px-4 py-3">
            Vous travaillez sans Internet. Tout est enregistré sur ce PC et sera envoyé automatiquement au retour de la connexion.
            <span class="opacity-80" x-text="`Données du ${heureDonnees()}.`"></span>
        </div>

        <template x-if="problemes.length > 0">
            <div class="mb-4 rounded-xl border border-(--color-error)/30 bg-(--color-error-tint) p-4 text-sm">
                <div class="font-semibold text-(--color-error) mb-2">À vérifier après l'envoi</div>
                <ul class="space-y-1.5">
                    <template x-for="(probleme, i) in problemes" :key="i">
                        <li class="flex items-start justify-between gap-3">
                            <span x-text="probleme.message"></span>
                            <button type="button" @click="stock.oublierProbleme(i)" class="flex-none text-xs font-semibold text-(--color-text-muted) hover:text-(--color-text-primary)">C'est noté</button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <nav class="flex flex-wrap gap-2 mb-5" aria-label="Comptoir">
            <template x-for="[cle, titre] in [['commande', 'Nouvelle commande'], ['commandes', 'Commandes'], ['clients', 'Clients']]" :key="cle">
                <button type="button" @click="onglet = cle" class="{{ $chip }}"
                        :class="onglet === cle ? 'bg-(--color-primary) border-(--color-primary) text-white' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-primary)'"
                        x-text="titre"></button>
            </template>
        </nav>

        {{-- ── Nouvelle commande : même écran que livewire/orders/create ── --}}
        <section x-show="onglet === 'commande'" @keydown.window="clavier($event)">
            <template x-if="!peut.create">
                <div class="rounded-xl bg-(--color-surface) border border-(--color-border) p-5 text-sm text-(--color-text-secondary)">Votre rôle ne permet pas de créer de commande.</div>
            </template>

            <div x-show="peut.create" class="max-w-6xl mx-auto">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                    <div>
                        <h1 class="font-display text-2xl font-bold mb-1">Nouvelle commande</h1>
                        <p class="text-sm text-(--color-text-muted)">Client, pièces, puis <span class="font-semibold text-(--color-text-secondary)">Valider</span>. La date et le paiement sont déjà remplis.</p>
                    </div>
                    <p class="hidden lg:flex items-center gap-3 text-xs text-(--color-text-muted)">
                        <span><kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">/</kbd> rechercher</span>
                        <span><kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">Entrée</kbd> valider</span>
                    </p>
                </div>

                {{-- Commande tout juste validée : le reçu à remettre au client --}}
                <template x-if="commandeEnregistree">
                    <div class="mb-5 flex flex-wrap items-center gap-3 p-4 rounded-xl bg-(--color-success-tint) border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)]">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M20 6 9 17l-5-5"></path></svg>
                        <div class="flex-1 min-w-0 text-sm">
                            <span class="font-semibold text-(--color-success-text)" x-text="`Commande ${commandeEnregistree.number} enregistrée.`"></span>
                            <span class="text-(--color-text-secondary)" x-text="`${commandeEnregistree.client?.name || ''} · ${fcfa(commandeEnregistree.total)} F. L'écran est prêt pour le client suivant.`"></span>
                        </div>
                        <button type="button" @click="derniereCommande = null" class="text-sm font-medium text-(--color-text-secondary) hover:underline">Fermer</button>
                        <button type="button" @click="imprimerRecu(commandeEnregistree)"
                                class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-success) text-white text-sm font-semibold hover:opacity-90">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
                            Imprimer le reçu
                        </button>
                    </div>
                </template>

                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-5 items-start">
                    <div class="flex flex-col gap-5 min-w-0">

                        {{-- 1. Le client --}}
                        <section class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5">
                            <template x-if="client">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="w-10 h-10 rounded-full bg-(--color-primary) text-white flex items-center justify-center text-sm font-semibold flex-none" x-text="initiales(client.name)"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</div>
                                        <div class="text-sm font-semibold truncate"><span x-text="client.name"></span> <span class="font-normal text-(--color-text-secondary) tabular-nums" x-text="`· ${formaterTelephone(client.phone)}`"></span></div>
                                    </div>
                                    <template x-if="derniereDuClient">
                                        <button type="button" @click="refaireDerniereCommande()"
                                                class="order-last sm:order-none max-sm:basis-full inline-flex items-center justify-center gap-1.5 h-10 px-3 rounded-lg border border-(--color-primary) text-(--color-primary) text-[13px] font-semibold hover:bg-(--color-primary-tint)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg>
                                            Refaire sa dernière commande
                                            <span class="font-normal text-(--color-text-secondary)" x-text="`(${derniereDuClient.reduce((s, l) => s + l[7], 0)} pièces)`"></span>
                                        </button>
                                    </template>
                                    <button type="button" @click="client = null; $nextTick(() => $refs.rechercheClient?.focus())" class="text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-primary) hover:underline">Changer</button>
                                </div>
                            </template>
                            <div x-show="!client">
                                <label for="client-search" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">
                                    <span class="w-5 h-5 rounded-full bg-(--color-primary) text-white text-[11px] flex items-center justify-center">1</span> Client
                                </label>
                                <div class="relative">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                                    <input id="client-search" x-ref="rechercheClient" type="search" autocomplete="off" x-model="recherche"
                                           @keydown.enter.prevent="choisirPremierClient()" placeholder="Nom ou numéro de téléphone"
                                           class="w-full h-12 pl-10 pr-3 rounded-xl border-2 border-(--color-border) bg-(--color-surface) text-[15px] focus:outline-none focus:border-(--color-primary)">
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <template x-for="(c, i) in clientsTrouves" :key="c.cle">
                                        <button type="button" @click="choisirClient(c)"
                                                class="flex items-center gap-2.5 pl-1.5 pr-3.5 py-1.5 rounded-full border border-(--color-border) text-left transition-all duration-150 hover:border-(--color-primary) hover:bg-(--color-primary-tint)"
                                                :class="i === 0 && 'border-(--color-primary)'">
                                            <span class="w-8 h-8 rounded-full bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center text-xs font-semibold flex-none" x-text="initiales(c.name)"></span>
                                            <span class="min-w-0">
                                                <span class="block text-[13px] font-semibold leading-tight" x-text="c.name"></span>
                                                <span class="block text-[11.5px] text-(--color-text-secondary) tabular-nums leading-tight" x-text="formaterTelephone(c.phone)"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <button type="button" x-show="!nouveauClient.ouvert" @click="ouvrirNouveauClient()"
                                            class="flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-dashed border-(--color-border) text-[13px] font-semibold text-(--color-primary) hover:border-(--color-primary)"
                                            x-text="recherche.trim() !== '' && clientsTrouves.length === 0 ? `+ Créer « ${recherche.trim().slice(0, 24)} »` : '+ Nouveau client'"></button>
                                </div>
                                <p x-show="recherche.trim() !== '' && clientsTrouves.length === 0 && !nouveauClient.ouvert" class="mt-2 text-xs text-(--color-text-muted)">Aucun client trouvé : créez-le en un toucher.</p>

                                <form x-show="nouveauClient.ouvert" @submit.prevent="creerClient()" class="mt-4 border border-(--color-border) rounded-xl p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <input id="hl-tel" type="tel" inputmode="tel" x-model="nouveauClient.telephone" placeholder="07 00 00 00 00" aria-label="Téléphone" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                    <input id="hl-prenom" type="text" x-model="nouveauClient.prenom" placeholder="Prénom" aria-label="Prénom" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                    <input id="hl-nom" type="text" x-model="nouveauClient.nom" placeholder="Nom" aria-label="Nom" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                    <p class="sm:col-span-3 text-xs text-(--color-error)" x-show="nouveauClient.erreur" x-text="nouveauClient.erreur"></p>
                                    <p class="sm:col-span-3 text-xs text-(--color-text-muted)">En installant l'application PressLink avec ce numéro, le client retrouvera toutes ses commandes.</p>
                                    <div class="sm:col-span-3 flex justify-end gap-2">
                                        <button type="button" @click="nouveauClient.ouvert = false" class="h-10 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                                        <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Créer et choisir ce client</button>
                                    </div>
                                </form>
                            </div>
                        </section>

                        {{-- 2. Le service et les pièces --}}
                        <section class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">
                                <span class="w-5 h-5 rounded-full bg-(--color-primary) text-white text-[11px] flex items-center justify-center">2</span> Service et pièces
                            </div>
                            <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 mb-4" role="tablist" aria-label="Service">
                                <template x-for="p in donnees.prestations" :key="p.id">
                                    <button type="button" role="tab" @click="prestationId = p.id" :aria-selected="prestationId === p.id"
                                            class="flex-none flex items-center gap-2 h-12 pl-1.5 pr-4 rounded-xl border-2 text-sm font-semibold transition-all duration-150 active:scale-[.97]"
                                            :class="prestationId === p.id ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-primary)'">
                                        <span x-html="icone('service', p.icon, 34)"></span>
                                        <span x-text="p.name"></span>
                                    </button>
                                </template>
                            </div>

                            {{-- Pinceau : la couleur des prochaines pièces --}}
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-4 px-3 py-2.5 rounded-xl bg-(--color-bg)">
                                <span class="text-[13px] font-medium text-(--color-text-secondary)">
                                    Couleur des pièces :
                                    <span class="font-semibold" :class="couleur ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)'" x-text="couleur || 'aucune'"></span>
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Couleur">
                                    <template x-for="[nom, hex] in Object.entries(couleurs.presets || {})" :key="nom">
                                        <button type="button" @click="choisirCouleur(nom)" :title="nom" :aria-label="nom" :aria-pressed="couleur === nom"
                                                class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110"
                                                :class="couleur === nom && 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)'" :style="`background-color: ${hex}`">
                                            <svg x-show="couleur === nom" width="13" height="13" viewBox="0 0 24 24" fill="none" :stroke="['Blanc', 'Jaune', 'Beige', 'Gris'].includes(nom) ? '#111' : '#fff'" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                                        </button>
                                    </template>
                                    <button type="button" @click="choisirCouleur(couleurs.multi)" title="Multicolore" aria-label="Multicolore"
                                            class="w-7 h-7 rounded-full border border-(--color-border) transition-transform duration-150 hover:scale-110"
                                            :class="couleur === couleurs.multi && 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)'"
                                            style="background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)"></button>
                                    <label title="Autre couleur" class="relative w-7 h-7 rounded-full border border-dashed border-(--color-text-muted) flex items-center justify-center cursor-pointer overflow-hidden transition-transform duration-150 hover:scale-110 text-(--color-text-secondary)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                        <input type="color" value="#2563eb" @change="couleur = couleurProche($event.target.value)" aria-label="Autre couleur" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                                    </label>
                                </div>
                            </div>

                            <p x-show="piecesDuService.length === 0" class="rounded-xl border border-dashed border-(--color-border) p-5 text-sm text-(--color-text-secondary) mb-4"
                               x-text="`Aucune pièce n'a de prix pour « ${prestation?.name || ''} ».`"></p>

                            <div x-show="piecesDuService.length > 8" class="relative mb-3">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                                <input x-ref="recherchePiece" type="search" x-model="rechercherPiece" @keydown.enter.prevent="ajouterPremierePiece()" placeholder="Chercher une pièce (Entrée pour l'ajouter)"
                                       class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                            </div>

                            <div class="grid grid-cols-3 sm:grid-cols-4 xl:grid-cols-5 gap-2.5">
                                <template x-for="piece in piecesDuService" :key="`${piece.id}-${prestationId}`">
                                    <div class="relative" x-show="pieceVisible(piece)">
                                        <button type="button" @click="ajouterPiece(piece)"
                                                class="w-full flex flex-col items-center gap-1 rounded-2xl border-2 px-1.5 pt-3 pb-2.5 text-center transition-all duration-150 active:scale-[.96]"
                                                :class="quantiteDe(piece.id) > 0 ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)'">
                                            <span x-html="icone('piece', piece.icon, 40)"></span>
                                            <span class="text-[13px] font-semibold leading-tight" x-text="piece.name"></span>
                                            <span class="text-[11.5px] text-(--color-text-secondary) tabular-nums" x-text="`${piece.variantes.length ? 'dès ' : ''}${fcfa(piece.aPartirDe)} F`"></span>
                                        </button>
                                        <template x-if="piece.variantes.length === 0 && quantiteDe(piece.id) > 0">
                                            <div>
                                                <button type="button" @click="ouvrirQuantite(piece)" :aria-label="`Taper la quantité de ${piece.name}`" title="Taper la quantité"
                                                        class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface) transition-transform hover:scale-110"
                                                        x-text="quantiteDe(piece.id)"></button>
                                                <button type="button" @click="retirerPiece(piece.id)" :aria-label="`Retirer une ${piece.name}`"
                                                        class="absolute top-1.5 left-1.5 w-7 h-7 rounded-full bg-(--color-surface) border border-(--color-border) text-sm font-bold leading-none flex items-center justify-center hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                            </div>
                                        </template>
                                        <span x-show="piece.variantes.length > 0 && quantiteDe(piece.id) > 0"
                                              class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface)"
                                              x-text="quantiteDe(piece.id)"></span>
                                    </div>
                                </template>
                                <button type="button" @click="autre.ouvert = true"
                                        class="flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-(--color-border) px-1.5 py-3 text-center text-[13px] font-semibold text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) transition-colors">
                                    <span class="w-10 h-10 rounded-2xl bg-(--color-bg) flex items-center justify-center text-xl">+</span>
                                    Autre pièce
                                </button>
                            </div>
                            <p class="mt-3 text-xs text-(--color-text-muted)">Touchez une pièce pour en ajouter une. Touchez ensuite son nombre pour taper la quantité d'un coup (ex. 12).</p>
                        </section>
                    </div>

                    {{-- Le ticket, toujours visible --}}
                    <aside class="lg:sticky lg:top-20 bg-(--color-surface) border border-(--color-border) rounded-xl p-4 lg:p-5">
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="font-semibold">Ticket</h2>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-(--color-text-secondary)" x-text="`${nombrePieces} pièce${nombrePieces > 1 ? 's' : ''}`"></span>
                                <button type="button" x-show="lignes.length" @click="if (confirm('Vider le ticket ?')) viderTicket()" class="text-(--color-text-muted) hover:text-(--color-error) hover:underline">Vider</button>
                            </div>
                        </div>

                        <p x-show="lignes.length === 0" class="text-sm text-(--color-text-muted) py-6 text-center border-y border-dashed border-(--color-border)">Les pièces touchées apparaissent ici.</p>
                        <div x-show="lignes.length" class="flex flex-col border-y border-(--color-border) divide-y divide-(--color-border)">
                            <template x-for="(ligne, i) in lignes" :key="`${i}-${ligne.base}-${ligne.prestation_id}-${ligne.color}`">
                                <div class="py-2.5">
                                    <div class="flex items-start gap-2.5">
                                        <button type="button" @click="couleursLigne = couleursLigne === i ? null : i" :title="ligne.color ? `Couleur : ${ligne.color}` : 'Ajouter une couleur'" aria-label="Couleur de la ligne"
                                                class="mt-0.5 w-5 h-5 flex-none rounded-full border" :class="ligne.color ? 'border-(--color-border)' : 'border-dashed border-(--color-text-muted)'" :style="fondCouleur(ligne.color)"></button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold leading-tight" x-text="ligne.base"></span>
                                                <span class="text-sm font-semibold tabular-nums" x-text="fcfa(ligne.unit_price_fcfa * ligne.quantity)"></span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 mt-1">
                                                <span class="text-xs text-(--color-text-secondary) min-w-0" x-text="`${[ligne.prestation_name, ligne.color].filter(Boolean).join(' · ')} · ${fcfa(ligne.unit_price_fcfa)} F`"></span>
                                                <span class="flex items-center gap-1 flex-none">
                                                    <button type="button" @click="changerQuantite(i, ligne.quantity - 1)" aria-label="Retirer une unité" class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                                    <input type="number" inputmode="numeric" min="0" max="999" :value="ligne.quantity" aria-label="Quantité"
                                                           @change="changerQuantite(i, parseInt($event.target.value) || 0)" @focus="$event.target.select()"
                                                           class="w-11 h-7 rounded-lg border border-(--color-border) bg-(--color-surface) text-center text-sm font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                                    <button type="button" @click="changerQuantite(i, ligne.quantity + 1)" aria-label="Ajouter une unité" class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">+</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-show="couleursLigne === i" class="mt-2 p-2 rounded-lg bg-(--color-bg) flex flex-wrap items-center gap-1.5">
                                        <template x-for="[nom, hex] in Object.entries(couleurs.presets || {})" :key="nom">
                                            <button type="button" @click="couleurDeLigne(i, nom)" :title="nom" :aria-label="nom"
                                                    class="w-7 h-7 rounded-full border border-(--color-border) transition-transform duration-150 hover:scale-110"
                                                    :class="ligne.color === nom && 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)'" :style="`background-color: ${hex}`"></button>
                                        </template>
                                        <button type="button" @click="couleurDeLigne(i, couleurs.multi)" title="Multicolore" aria-label="Multicolore"
                                                class="w-7 h-7 rounded-full border border-(--color-border)" :class="ligne.color === couleurs.multi && 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)'"
                                                style="background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)"></button>
                                        <label title="Autre couleur" class="relative w-7 h-7 rounded-full border border-dashed border-(--color-text-muted) flex items-center justify-center cursor-pointer overflow-hidden text-(--color-text-secondary)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                            <input type="color" value="#2563eb" @change="couleurDeLigne(i, couleurProche($event.target.value))" aria-label="Autre couleur" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Prête le / remarque --}}
                        <div class="mt-4 flex flex-col gap-2.5 text-sm">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-(--color-text-secondary)">Prête le</span>
                                <input type="date" x-model="dateRetrait" class="h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                            </label>
                            <button type="button" x-show="!noteOuverte" @click="noteOuverte = true; $nextTick(() => $refs.note.focus())" class="self-start text-[13px] font-medium text-(--color-primary) hover:underline">+ Ajouter une remarque</button>
                            <textarea x-show="noteOuverte" x-ref="note" x-model="note" rows="2" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm"></textarea>
                        </div>

                        {{-- Paiement : « Au retrait » par défaut --}}
                        <div x-show="total > 0 && peut.pay" class="mt-4">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Paiement</div>
                            <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-(--color-bg)" role="radiogroup" aria-label="Paiement">
                                <template x-for="[cle, titre] in [['later', 'Au retrait'], ['full', 'Payée'], ['partial', 'Acompte']]" :key="cle">
                                    <button type="button" role="radio" :aria-checked="paiement === cle" @click="paiement = cle; erreur = null"
                                            class="h-9 rounded-lg text-[13px] font-semibold transition-colors"
                                            :class="paiement === cle ? 'bg-(--color-surface) text-(--color-primary) shadow-sm' : 'text-(--color-text-secondary) hover:text-(--color-text-primary)'" x-text="titre"></button>
                                </template>
                            </div>
                            <div x-show="paiement === 'partial'" class="mt-2.5">
                                <div class="relative">
                                    <input type="number" inputmode="numeric" min="1" step="50" x-model="acompte" aria-label="Montant versé" placeholder="Montant versé"
                                           class="w-full h-10 pl-3 pr-14 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                                </div>
                                <button type="button" x-show="moitie > 0 && moitie < total" @click="acompte = String(moitie)" class="mt-1.5 text-xs font-medium text-(--color-primary) hover:underline" x-text="`La moitié : ${fcfa(moitie)} F`"></button>
                            </div>
                            <div x-show="paiement !== 'later'" class="mt-2.5 flex flex-wrap gap-1.5">
                                <template x-for="m in donnees.payment_methods" :key="m.value">
                                    <button type="button" @click="mode = m.value" class="h-8 px-3 rounded-full border text-xs font-medium transition-colors"
                                            :class="mode === m.value ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)'" x-text="m.label"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Total --}}
                        <div class="mt-4 pt-3 border-t border-dashed border-(--color-border)">
                            <div class="flex justify-between items-baseline">
                                <span class="text-sm text-(--color-text-secondary)">Total</span>
                                <span class="font-display text-2xl font-bold tabular-nums" x-text="`${fcfa(total)} F`"></span>
                            </div>
                            <div x-show="total > 0 && montantPaye > 0" class="flex justify-between text-xs mt-1">
                                <span class="text-(--color-text-secondary)">Reste à payer au retrait</span>
                                <span class="font-semibold tabular-nums" :class="total - montantPaye > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)'"
                                      x-text="total - montantPaye > 0 ? `${fcfa(total - montantPaye)} F` : 'Rien, soldée'"></span>
                            </div>
                        </div>

                        <div x-show="erreur" class="mt-3 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm" x-text="erreur"></div>

                        <button type="button" @click="enregistrerCommande()"
                                class="mt-4 w-full py-3.5 rounded-xl text-[15px] font-semibold flex items-center justify-center gap-2 transition-colors"
                                :class="pret ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600) shadow-sm' : 'bg-(--color-primary-tint) text-(--color-primary)'">
                            <svg x-show="pret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            <span x-text="libelleValider"></span>
                        </button>
                    </aside>
                </div>
            </div>

            {{-- Variantes d'une pièce (manche courte / longue…) --}}
            <template x-if="variantesDe">
                <div class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="variantesDe = null">
                    <div class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl">
                        <div class="flex items-center gap-3 mb-4">
                            <span x-html="icone('piece', variantesDe.icon, 40)"></span>
                            <p class="flex-1 font-semibold" x-text="`${variantesDe.name} : laquelle ?`"></p>
                            <button type="button" @click="variantesDe = null" class="text-sm text-(--color-text-secondary) hover:underline">Annuler</button>
                        </div>
                        <div class="flex flex-col gap-2">
                            <template x-for="v in variantesDe.variantes" :key="v.id">
                                <button type="button" @click="ajouterVariante(v)"
                                        class="h-12 px-4 rounded-xl border-2 border-(--color-border) text-[15px] font-semibold flex items-center justify-between hover:border-(--color-primary) active:scale-[.98] transition">
                                    <span x-text="v.name"></span>
                                    <span class="text-(--color-text-secondary) font-medium tabular-nums" x-text="`${fcfa(v.prix)} F`"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Pièce hors catalogue --}}
            <div x-show="autre.ouvert" class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="autre.ouvert = false">
                <form @submit.prevent="ajouterAutre()" class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl">
                    <p class="font-semibold mb-4" x-text="`Autre pièce · ${prestation?.name || ''}`"></p>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5" for="hl-autre-nom">Nom de la pièce</label>
                    <input id="hl-autre-nom" type="text" x-model="autre.nom" placeholder="Ex. Nappe brodée" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5" for="hl-autre-prix">Prix (FCFA)</label>
                    <input id="hl-autre-prix" type="number" inputmode="numeric" min="0" x-model="autre.prix" placeholder="1000" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" @click="autre.ouvert = false" class="h-11 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                        <button type="submit" class="h-11 px-5 rounded-xl bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter au ticket</button>
                    </div>
                </form>
            </div>

            {{-- Quantité tapée d'un coup (« 12 chemises ») --}}
            <div x-show="quantite.ouvert" class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="quantite.ouvert = false">
                <form @submit.prevent="validerQuantite()" class="w-full max-w-xs bg-(--color-surface) rounded-2xl p-5 shadow-xl">
                    <p class="font-semibold mb-3">Combien : <span x-text="quantite.nom"></span> ?</p>
                    <input x-ref="quantite" type="number" inputmode="numeric" min="0" max="999" x-model="quantite.valeur" aria-label="Quantité"
                           class="w-full h-14 rounded-xl border-2 border-(--color-border) bg-(--color-surface) text-center text-2xl font-bold tabular-nums focus:outline-none focus:border-(--color-primary)">
                    <div class="grid grid-cols-4 gap-2 mt-3">
                        <template x-for="n in [2, 5, 10, 20]" :key="n">
                            <button type="button" @click="quantite.valeur = String(n)" class="h-10 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)" x-text="n"></button>
                        </template>
                    </div>
                    <button type="submit" class="mt-4 w-full h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">OK</button>
                </form>
            </div>
        </section>

        {{-- ── Commandes ───────────────────────────────────────────────── --}}
        <section x-show="onglet === 'commandes'">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <template x-for="[cle, titre] in [['en_cours', 'En cours'], ['pretes', 'Prêtes'], ['recuperees', 'Récupérées'], ['toutes', 'Toutes']]" :key="cle">
                    <button type="button" @click="filtre = cle" class="{{ $chip }}"
                            :class="filtre === cle ? 'bg-(--color-primary-tint) border-(--color-primary) text-(--color-primary)' : 'border-(--color-border) bg-(--color-surface)'">
                        <span x-text="titre"></span><span class="text-xs opacity-70" x-text="compter(cle)"></span>
                    </button>
                </template>
                <div class="flex-1"></div>
                <input type="search" x-model="rechercheCommandes" placeholder="N°, client, téléphone" aria-label="Rechercher une commande" class="{{ $input }} w-full sm:w-64">
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl divide-y divide-(--color-border)">
                <p x-show="commandesFiltrees.length === 0" class="p-5 text-sm text-(--color-text-muted)">Aucune commande.</p>
                <template x-for="c in commandesFiltrees.slice(0, 200)" :key="c.cle">
                    <div>
                        <button type="button" @click="ouvrir(c)" class="w-full grid grid-cols-[1fr_auto] sm:grid-cols-[120px_1fr_130px_110px_110px] items-center gap-x-4 gap-y-1 px-4 py-3 text-left hover:bg-(--color-bg)">
                            <span class="font-semibold text-sm tabular-nums" x-text="c.number"></span>
                            <span class="text-sm truncate" x-text="c.client?.name || '—'"></span>
                            <span>
                                {{-- Même pastille que <x-status-badge> en ligne --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide" :style="styleStatut(c.status)">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background: currentColor"></span>
                                    <span x-text="libelleStatut(c.status)"></span>
                                </span>
                            </span>
                            <span class="text-sm tabular-nums sm:text-right" x-text="`${fcfa(c.total)} F`"></span>
                            <span class="text-xs sm:text-right" :class="c.reste > 0 ? 'text-(--color-warning-text) font-semibold' : 'text-(--color-text-muted)'"
                                  x-text="c.enAttente ? 'À envoyer' : (c.reste > 0 ? `Reste ${fcfa(c.reste)} F` : 'Réglée')"></span>
                        </button>

                        <template x-if="ouverte === c.cle">
                            <div class="px-4 pb-5 pt-1 grid grid-cols-1 md:grid-cols-2 gap-5 bg-(--color-bg)/50">
                                <div>
                                    <div class="text-xs text-(--color-text-muted) mb-2" x-text="`Déposée le ${dateCourte(c.dropped_off_at)}${c.expected_at ? ' · retrait prévu le ' + dateCourte(c.expected_at + 'T12:00:00') : ''}`"></div>
                                    <ul class="text-sm space-y-1">
                                        <template x-for="(item, i) in c.items" :key="i">
                                            <li class="flex justify-between gap-3"><span x-text="`${item.quantity} × ${item.name}`"></span><span class="tabular-nums" x-text="`${fcfa(item.subtotal)} F`"></span></li>
                                        </template>
                                    </ul>
                                    <div class="mt-2 pt-2 border-t border-(--color-border) text-sm flex justify-between"><span>Payé</span><span class="tabular-nums" x-text="`${fcfa(c.paid)} F / ${fcfa(c.total)} F`"></span></div>
                                    <button type="button" @click="imprimerRecu(c)" class="mt-3 text-sm font-semibold text-(--color-primary) hover:underline">Imprimer le reçu</button>
                                </div>

                                <div class="space-y-3">
                                    <div x-show="peut.workshop" class="flex flex-wrap gap-2">
                                        <button type="button" x-show="['recue', 'attente'].includes(c.status)" @click="changerStatut(c, 'traitement')" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Démarrer le traitement</button>
                                        <button type="button" x-show="c.status === 'traitement'" @click="changerStatut(c, 'prete')" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Marquer prête</button>
                                    </div>

                                    <form x-show="peut.pay && c.reste > 0 && !['recuperee', 'annulee'].includes(c.status)" @submit.prevent="encaisser(c)" class="rounded-lg border border-(--color-border) bg-(--color-surface) p-3">
                                        <div class="text-sm font-semibold mb-2">Encaisser</div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="number" min="1" x-model="encaissement.montant" aria-label="Montant encaissé" class="{{ $input }}">
                                            <select x-model="encaissement.mode" aria-label="Moyen de paiement" class="{{ $input }}">
                                                <template x-for="m in donnees.payment_methods" :key="m.value"><option :value="m.value" x-text="m.label"></option></template>
                                            </select>
                                        </div>
                                        <p class="text-xs text-(--color-error) mt-1" x-show="encaissement.erreur" x-text="encaissement.erreur"></p>
                                        <button type="submit" class="mt-2 h-9 px-4 rounded-lg border border-(--color-primary) text-(--color-primary) text-sm font-semibold">Encaisser</button>
                                    </form>

                                    <div x-show="peut.pay && c.status === 'prete'" class="rounded-lg border border-(--color-border) bg-(--color-surface) p-3">
                                        <div class="text-sm font-semibold mb-1">Remettre au client</div>
                                        <p x-show="droitDe(c) > 0" class="text-xs text-(--color-warning-text) mb-2" x-text="`Droit de magasinage estimé : ${fcfa(droitDe(c))} F (calculé exactement à l'envoi).`"></p>
                                        <label x-show="droitDe(c) > 0 && peut.waive" class="flex items-center gap-2 text-sm mb-2"><input type="checkbox" x-model="remise.offrir" class="accent-(--color-primary)"> Offrir le droit de magasinage</label>
                                        <div x-show="aPayerALaRemise(c) > 0" class="mb-2">
                                            <div class="text-sm mb-1.5" x-text="`À encaisser : ${fcfa(aPayerALaRemise(c))} F`"></div>
                                            <select x-model="remise.mode" aria-label="Moyen de paiement du solde" class="{{ $input }}">
                                                <template x-for="m in donnees.payment_methods" :key="m.value"><option :value="m.value" x-text="m.label"></option></template>
                                            </select>
                                        </div>
                                        <button type="button" @click="remettre(c)" class="h-10 px-4 rounded-lg bg-(--color-success) text-white text-sm font-semibold"
                                                x-text="aPayerALaRemise(c) > 0 ? 'Encaisser et remettre' : 'Remettre au client'"></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        {{-- ── Clients ─────────────────────────────────────────────────── --}}
        <section x-show="onglet === 'clients'">
            <input type="search" x-model="rechercheClients" placeholder="Nom ou téléphone" aria-label="Rechercher un client" class="{{ $input }} sm:w-80 mb-4">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl divide-y divide-(--color-border)">
                <template x-for="c in clientsFiltres" :key="c.cle">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm"><span x-text="c.name"></span> <span x-show="c.local" class="text-xs text-(--color-info)">· à envoyer</span></div>
                            <div class="text-xs text-(--color-text-muted)" x-text="`${formaterTelephone(c.phone)} · ${commandesDu(c)} commande(s) récente(s)`"></div>
                        </div>
                        <button type="button" x-show="peut.create" @click="nouvelleCommandePour(c)" class="text-sm font-semibold text-(--color-primary) hover:underline">Nouvelle commande</button>
                    </div>
                </template>
            </div>
        </section>
    </main>
    </template>

    <div x-show="message" x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-30 max-w-md w-[calc(100%-2rem)] rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
         :class="message?.type === 'erreur' ? 'bg-(--color-error) text-white' : 'bg-(--color-text-primary) text-(--color-bg)'" x-text="message?.texte"></div>
</div>
@livewireScripts
</body>
</html>
