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
<div x-data="comptoir({{ $pressing->id }}, {{ $user->id }})" x-cloak class="min-h-screen flex flex-col">

    {{-- Barre du haut : état de la connexion et des envois --}}
    <header class="sticky top-0 z-20 bg-(--color-surface) border-b border-(--color-border)">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center gap-3">
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
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-5">
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

        {{-- ── Nouvelle commande ───────────────────────────────────────── --}}
        <section x-show="onglet === 'commande'">
            <template x-if="!peut.create">
                <div class="rounded-xl bg-(--color-surface) border border-(--color-border) p-5 text-sm text-(--color-text-secondary)">Votre rôle ne permet pas de créer de commande.</div>
            </template>

            <template x-if="commandeEnregistree">
                <div class="mb-5 rounded-xl bg-(--color-success-tint) text-(--color-success-text) px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <span class="font-semibold" x-text="`Commande ${commandeEnregistree.number} enregistrée`"></span>
                        <span x-text="` pour ${commandeEnregistree.client?.name || ''} · ${fcfa(commandeEnregistree.total)} F`"></span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="imprimerRecu(commandeEnregistree)" class="h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Imprimer le reçu</button>
                        <button type="button" @click="derniereCommande = null" class="h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold text-(--color-text-primary)">Fermer</button>
                    </div>
                </div>
            </template>

            <div x-show="peut.create" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Client --}}
                    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                        <h2 class="font-display text-base font-bold mb-3">1. Client</h2>
                        <template x-if="client">
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-(--color-primary-tint) px-4 py-3">
                                <div>
                                    <div class="font-semibold" x-text="client.name"></div>
                                    <div class="text-xs text-(--color-text-secondary)" x-text="formaterTelephone(client.phone)"></div>
                                </div>
                                <button type="button" @click="client = null" class="text-sm font-semibold text-(--color-primary) hover:underline">Changer</button>
                            </div>
                        </template>
                        <div x-show="!client">
                            <input type="search" x-model="recherche" placeholder="Nom ou téléphone du client" aria-label="Rechercher un client" class="{{ $input }}">
                            <div class="mt-2 divide-y divide-(--color-border)" x-show="clientsTrouves.length">
                                <template x-for="c in clientsTrouves" :key="c.cle">
                                    <button type="button" @click="choisirClient(c)" class="w-full flex items-center justify-between gap-3 px-2 py-2.5 text-left hover:bg-(--color-bg) rounded-lg">
                                        <span class="font-medium text-sm" x-text="c.name"></span>
                                        <span class="text-xs text-(--color-text-muted)" x-text="formaterTelephone(c.phone)"></span>
                                    </button>
                                </template>
                            </div>
                            <button type="button" x-show="!nouveauClient.ouvert" @click="ouvrirNouveauClient()" class="mt-3 text-sm font-semibold text-(--color-primary) hover:underline">+ Nouveau client</button>
                            <form x-show="nouveauClient.ouvert" @submit.prevent="creerClient()" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div><label class="{{ $label }}" for="hl-prenom">Prénom</label><input id="hl-prenom" type="text" x-model="nouveauClient.prenom" class="{{ $input }}"></div>
                                <div><label class="{{ $label }}" for="hl-nom">Nom</label><input id="hl-nom" type="text" x-model="nouveauClient.nom" class="{{ $input }}"></div>
                                <div><label class="{{ $label }}" for="hl-tel">Téléphone</label><input id="hl-tel" type="tel" x-model="nouveauClient.telephone" placeholder="07 00 00 00 00" class="{{ $input }}"></div>
                                <p class="sm:col-span-3 text-xs text-(--color-error)" x-show="nouveauClient.erreur" x-text="nouveauClient.erreur"></p>
                                <div class="sm:col-span-3 flex gap-2">
                                    <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter ce client</button>
                                    <button type="button" @click="nouveauClient.ouvert = false" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold">Annuler</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Pièces --}}
                    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                        <h2 class="font-display text-base font-bold mb-3">2. Service et pièces</h2>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="p in donnees.prestations" :key="p.id">
                                <button type="button" @click="prestationId = p.id; varianteDe = null" class="{{ $chip }}"
                                        :class="prestationId === p.id ? 'bg-(--color-primary-tint) border-(--color-primary) text-(--color-primary)' : 'border-(--color-border) hover:border-(--color-primary)'"
                                        x-text="p.name"></button>
                            </template>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
                            <template x-for="piece in piecesDuService" :key="piece.id">
                                <div class="contents">
                                    <button type="button" @click="ajouterPiece(piece)"
                                            class="relative flex flex-col items-start gap-0.5 rounded-xl border-2 px-3 py-2.5 text-left transition-colors"
                                            :class="quantiteDe(piece.id) > 0 ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)'">
                                        <span class="font-semibold text-sm" x-text="piece.name"></span>
                                        <span class="text-xs text-(--color-text-secondary)" x-text="piece.variantes.length ? `${piece.variantes.length} choix` : `${fcfa(piece.prix)} F`"></span>
                                        <span x-show="quantiteDe(piece.id) > 0" class="absolute top-1.5 right-1.5 min-w-6 h-6 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold grid place-items-center" x-text="quantiteDe(piece.id)"></span>
                                    </button>
                                    <template x-if="varianteDe === piece.id">
                                        <div class="col-span-full flex flex-wrap gap-2 rounded-xl bg-(--color-bg) p-3">
                                            <template x-for="v in piece.variantes" :key="v.id">
                                                <button type="button" @click="ajouterVariante(piece, v)" class="h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm hover:border-(--color-primary)"
                                                        x-text="`${v.name} · ${fcfa(v.prix)} F`"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" @click="autre.ouvert = !autre.ouvert" class="flex flex-col items-start justify-center rounded-xl border-2 border-dashed border-(--color-border) px-3 py-2.5 text-sm font-semibold text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">+ Autre pièce</button>
                        </div>
                        <form x-show="autre.ouvert" @submit.prevent="ajouterAutre()" class="mt-3 flex flex-wrap gap-2">
                            <input type="text" x-model="autre.nom" placeholder="Nom de la pièce" aria-label="Nom de la pièce" class="{{ $input }} flex-1 min-w-40">
                            <input type="number" min="0" x-model="autre.prix" placeholder="Prix (F)" aria-label="Prix" class="{{ $input }} w-32">
                            <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Ajouter</button>
                        </form>
                    </div>
                </div>

                {{-- Ticket --}}
                <aside class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 lg:sticky lg:top-20">
                    <h2 class="font-display text-base font-bold mb-3">3. Ticket</h2>
                    <p x-show="lignes.length === 0" class="text-sm text-(--color-text-muted)">Aucune pièce pour le moment.</p>
                    <ul class="divide-y divide-(--color-border)">
                        <template x-for="(ligne, i) in lignes" :key="i">
                            <li class="py-2 flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate" x-text="ligne.name"></div>
                                    <div class="text-xs text-(--color-text-muted)" x-text="`${fcfa(ligne.unit_price_fcfa)} F l'unité`"></div>
                                </div>
                                <button type="button" @click="changerQuantite(i, -1)" class="w-7 h-7 rounded-md border border-(--color-border)" aria-label="Une de moins">−</button>
                                <span class="w-6 text-center text-sm font-semibold tabular-nums" x-text="ligne.quantity"></span>
                                <button type="button" @click="changerQuantite(i, 1)" class="w-7 h-7 rounded-md border border-(--color-border)" aria-label="Une de plus">+</button>
                                <span class="w-20 text-right text-sm font-semibold tabular-nums" x-text="`${fcfa(ligne.unit_price_fcfa * ligne.quantity)} F`"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="flex items-baseline justify-between border-t border-(--color-border) mt-2 pt-3 mb-4">
                        <span class="font-semibold">Total</span>
                        <span class="font-display text-2xl font-bold tabular-nums" x-text="`${fcfa(total)} F`"></span>
                    </div>

                    <div class="space-y-3">
                        <div><label class="{{ $label }}" for="hl-retrait">Retrait prévu</label><input id="hl-retrait" type="date" x-model="dateRetrait" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="hl-note">Note (facultatif)</label><input id="hl-note" type="text" x-model="note" class="{{ $input }}"></div>
                        <div x-show="peut.pay && total > 0">
                            <div class="{{ $label }}">Paiement</div>
                            <div class="grid grid-cols-3 gap-1.5 mb-2">
                                <template x-for="[cle, titre] in [['plus_tard', 'Plus tard'], ['acompte', 'Acompte'], ['payee', 'Payée']]" :key="cle">
                                    <button type="button" @click="paiement = cle" class="h-9 rounded-lg border text-sm font-semibold"
                                            :class="paiement === cle ? 'border-(--color-primary) bg-(--color-primary-tint) text-(--color-primary)' : 'border-(--color-border)'" x-text="titre"></button>
                                </template>
                            </div>
                            <div x-show="paiement !== 'plus_tard'" class="grid grid-cols-2 gap-2">
                                <input x-show="paiement === 'acompte'" type="number" min="1" x-model="acompte" placeholder="Montant (F)" aria-label="Montant de l'acompte" class="{{ $input }}">
                                <select x-model="mode" aria-label="Moyen de paiement" class="{{ $input }}" :class="paiement === 'payee' && 'col-span-2'">
                                    <template x-for="m in donnees.payment_methods" :key="m.value"><option :value="m.value" x-text="m.label"></option></template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p x-show="erreur" class="mt-3 text-sm text-(--color-error)" x-text="erreur"></p>
                    <button type="button" @click="enregistrerCommande()" class="mt-4 w-full h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">Enregistrer la commande</button>
                </aside>
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
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full text-xs font-semibold"
                                      :class="{ 'bg-(--color-info-tint) text-(--color-info)': ['recue', 'attente'].includes(c.status), 'bg-(--color-warning-tint) text-(--color-warning-text)': c.status === 'traitement', 'bg-(--color-success-tint) text-(--color-success-text)': c.status === 'prete', 'bg-(--color-bg) text-(--color-text-secondary)': ['recuperee', 'annulee'].includes(c.status) }"
                                      x-text="libelleStatut(c.status)"></span>
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
