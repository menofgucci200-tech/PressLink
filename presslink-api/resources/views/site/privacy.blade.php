@php $legal = config('presslink.legal'); @endphp
<x-site.legal
    title="Politique de confidentialité — PressLink"
    heading="Politique de confidentialité"
    description="Quelles données PressLink collecte, pourquoi, avec qui elles sont partagées, combien de temps elles sont conservées et comment exercer vos droits."
    path="/confidentialite">

    <p class="mt-8">Cette politique explique comment <strong>{{ $legal['company'] }}</strong>, éditeur de PressLink, traite vos données personnelles, conformément à la <strong>loi ivoirienne n° 2013-450 du 19 juin 2013</strong> relative à la protection des données à caractère personnel, sous le contrôle de l'ARTCI (Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire).</p>

    <h2>1. Responsable du traitement</h2>
    <p>{{ $legal['company'] }}, {{ $legal['address'] }}. Contact : {{ config('presslink.support.phone_display') }} (appel ou WhatsApp)@if ($legal['email']), <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>@endif.</p>
    <p>Chaque pressing est par ailleurs responsable de l'usage qu'il fait des données de ses propres clients dans le cadre de son activité.</p>

    <h2>2. Données collectées</h2>
    <h3>Clients des pressings (application)</h3>
    <ul>
        <li><strong>Identité et contact</strong> : prénom, nom, numéro de téléphone, genre, email (facultatif), photo de profil (facultative).</li>
        <li><strong>Compte</strong> : mot de passe (stocké chiffré, jamais en clair), date de dernière connexion.</li>
        <li><strong>Commandes</strong> : pressings rejoints, articles déposés, montants, statuts et historique, signalements de problème et leurs messages.</li>
        <li><strong>Notifications</strong> : identifiant technique de l'appareil permettant l'envoi des notifications, et vos préférences de notification.</li>
    </ul>
    <h3>Personnel des pressings (espace pressing)</h3>
    <ul>
        <li>Nom, identifiant de connexion, téléphone, email, rôle, mot de passe chiffré.</li>
        <li>Informations du pressing : nom, adresse, horaires, téléphone, services et tarifs, abonnement.</li>
    </ul>
    <h3>Données techniques</h3>
    <p>Journaux techniques du serveur (adresse IP, date, page demandée) pour la sécurité et le diagnostic des pannes.</p>

    <h2>3. Pourquoi nous utilisons vos données</h2>
    <ul>
        <li><strong>Fournir le service</strong> : créer et sécuriser votre compte, afficher vos commandes et leur avancement (exécution des CGU).</li>
        <li><strong>Vous prévenir</strong> : notifications liées à vos commandes (exécution des CGU ; désactivables).</li>
        <li><strong>Permettre au pressing de gérer vos commandes</strong> (exécution des CGU).</li>
        <li><strong>Assistance</strong> : répondre à vos demandes de support et traiter les signalements.</li>
        <li><strong>Sécurité</strong> : prévenir les fraudes et les accès non autorisés (intérêt légitime).</li>
    </ul>
    <p>Vos données ne sont <strong>jamais vendues</strong> et ne sont pas utilisées à des fins publicitaires.</p>

    <h2>4. Qui a accès à vos données</h2>
    <ul>
        <li><strong>Les pressings que vous rejoignez</strong> : votre nom, votre téléphone et vos commandes chez eux. Un pressing ne voit pas vos commandes chez un autre pressing.</li>
        <li><strong>L'équipe PressLink</strong>, uniquement pour l'assistance, la maintenance et la sécurité.</li>
        <li><strong>Nos prestataires techniques</strong>, qui agissent sur nos instructions :
            <ul>
                <li>LWS (France) — hébergement du site, de l'application et de la base de données ;</li>
                <li>OneSignal (États-Unis) — envoi des notifications sur votre téléphone (identifiant de l'appareil uniquement).</li>
            </ul>
        </li>
        <li><strong>Les autorités</strong>, uniquement sur demande légale.</li>
    </ul>
    <p>Si vous contactez le support par WhatsApp, la conversation est également soumise aux conditions de WhatsApp (Meta).</p>

    <h2>5. Transferts hors de Côte d'Ivoire</h2>
    <p>L'hébergement (France) et l'envoi des notifications (États-Unis) impliquent un transfert de données hors de Côte d'Ivoire. Ces transferts sont limités au strict nécessaire et encadrés par les engagements contractuels de nos prestataires en matière de sécurité et de confidentialité, conformément à la loi n° 2013-450.</p>

    <h2>6. Durée de conservation</h2>
    <ul>
        <li><strong>Compte client</strong> : tant que le compte est actif, puis suppression ou anonymisation dans un délai d'un mois après une demande de suppression.</li>
        <li><strong>Commandes</strong> : conservées par le pressing pour sa gestion et ses obligations comptables, jusqu'à 10 ans.</li>
        <li><strong>Journaux techniques</strong> : 12 mois au maximum.</li>
        <li><strong>Codes de vérification</strong> : quelques minutes, puis supprimés.</li>
    </ul>

    <h2>7. Sécurité</h2>
    <p>Connexions chiffrées (HTTPS), mots de passe chiffrés, accès limités selon le rôle de chacun (client, employé, administrateur), séparation des données entre pressings. Aucun système n'étant infaillible, nous vous invitons à choisir un mot de passe que vous n'utilisez nulle part ailleurs.</p>

    <h2>8. Cookies et stockage local</h2>
    <p>Le site et l'application utilisent uniquement des <strong>cookies et un stockage local strictement nécessaires</strong> : maintien de votre session de connexion, protection contre les requêtes frauduleuses, mémorisation de vos préférences (par exemple le masquage de la bannière d'installation). Aucun cookie publicitaire ni de mesure d'audience tierce n'est utilisé.</p>

    <h2>9. Vos droits</h2>
    <p>Conformément à la loi n° 2013-450, vous disposez d'un droit d'<strong>accès</strong>, de <strong>rectification</strong>, d'<strong>opposition</strong> et de <strong>suppression</strong> de vos données. Vous pouvez modifier la plupart de vos informations directement depuis votre profil dans l'application.</p>
    <p>Pour exercer vos droits ou supprimer votre compte, contactez le support au {{ config('presslink.support.phone_display') }} (appel ou WhatsApp)@if ($legal['email']) ou à <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>@endif. Nous pourrons vous demander de confirmer votre identité. Nous répondons dans un délai d'un mois.</p>
    <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir l'<strong>ARTCI</strong> (<a href="https://www.artci.ci" rel="noopener" target="_blank">www.artci.ci</a>).</p>

    <h2>10. Mineurs</h2>
    <p>Le service s'adresse aux personnes majeures. Un mineur ne doit l'utiliser qu'avec l'accord de son représentant légal.</p>

    <h2>11. Modifications</h2>
    <p>Cette politique peut être mise à jour. La date de dernière mise à jour figure en haut de la page ; en cas de changement important, vous en serez informé dans l'application.</p>
</x-site.legal>
