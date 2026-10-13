@php $legal = config('presslink.legal'); @endphp
<x-site.legal
    title="Conditions générales d'utilisation — PressLink"
    heading="Conditions générales d'utilisation"
    description="Conditions générales d'utilisation de l'application PressLink pour les clients et du logiciel de gestion pour les pressings."
    path="/conditions-generales">

    <h2>1. Objet</h2>
    <p>Les présentes conditions générales d'utilisation (CGU) encadrent l'accès et l'utilisation du service <strong>PressLink</strong>, édité par {{ $legal['company'] }} (l'« Éditeur »). PressLink comprend :</p>
    <ul>
        <li><strong>l'application client</strong> (presslink.org/app), qui permet aux clients des pressings de suivre leurs commandes et d'être notifiés ;</li>
        <li><strong>l'espace pressing</strong> (tableau de bord), logiciel de gestion destiné aux pressings partenaires et à leur personnel.</li>
    </ul>
    <p>Utiliser PressLink, c'est accepter les présentes CGU. Si vous ne les acceptez pas, n'utilisez pas le service.</p>

    <h2>2. Rôle de PressLink</h2>
    <p>PressLink est un outil de suivi et de gestion. <strong>PressLink ne réalise pas les prestations de pressing</strong> (nettoyage, repassage, retouche…) : elles sont exécutées sous la seule responsabilité du pressing que vous avez choisi. Les prix, délais, conditions de dépôt et de retrait, ainsi que les éventuelles réclamations sur la qualité ou la perte d'un article, relèvent de la relation entre le client et son pressing.</p>

    <h2>3. Accès au service</h2>
    <p>L'application client est <strong>gratuite</strong>. Elle s'utilise depuis un navigateur ou s'installe sur l'écran d'accueil du téléphone. L'accès à internet et les éventuels frais de connexion restent à la charge de l'utilisateur.</p>
    <p>L'espace pressing est accessible aux pressings ayant souscrit un abonnement auprès de l'Éditeur, selon l'offre convenue (plan, limites d'utilisation, tarifs).</p>

    <h2>4. Compte utilisateur</h2>
    <ul>
        <li>Le compte client est créé avec un numéro de téléphone, un prénom, un nom et un mot de passe. Un numéro de téléphone correspond à un seul compte.</li>
        <li>Vous vous engagez à fournir des informations exactes et à les tenir à jour.</li>
        <li>Vous êtes responsable de la confidentialité de votre mot de passe et des actions réalisées depuis votre compte. Prévenez le support sans délai en cas d'utilisation non autorisée.</li>
        <li>Les comptes du personnel des pressings sont créés par le pressing, qui reste responsable de leur usage et de leur désactivation.</li>
    </ul>

    <h2>5. Rejoindre un pressing</h2>
    <p>Le client rejoint un pressing grâce au code fourni par celui-ci (par exemple PE-4821). En le rejoignant, il accepte que ce pressing accède à son nom et à son numéro de téléphone pour gérer ses commandes. Il peut quitter un pressing à tout moment depuis l'application ; l'historique des commandes passées est conservé par le pressing pour sa gestion.</p>

    <h2>6. Engagements des utilisateurs</h2>
    <p>Il est interdit d'utiliser PressLink :</p>
    <ul>
        <li>à des fins illégales, frauduleuses ou portant atteinte aux droits de tiers ;</li>
        <li>pour publier des contenus injurieux, diffamatoires ou trompeurs, notamment dans les signalements de problème ;</li>
        <li>pour tenter d'accéder sans autorisation aux comptes, aux données ou aux systèmes du service, ou pour en perturber le fonctionnement.</li>
    </ul>

    <h2>7. Engagements des pressings</h2>
    <ul>
        <li>Enregistrer des informations de commande exactes (articles, prix, statuts) et tenir à jour leurs informations pratiques (adresse, horaires, téléphone).</li>
        <li>N'utiliser les données des clients que pour la gestion de leurs commandes et la relation client, dans le respect de la loi n° 2013-450 relative à la protection des données à caractère personnel.</li>
        <li>Garder confidentiels les identifiants de leur personnel et retirer les accès des personnes qui ne travaillent plus pour eux.</li>
    </ul>

    <h2>8. Notifications</h2>
    <p>PressLink envoie des notifications liées à vos commandes (commande enregistrée, prête, etc.). Vous pouvez les désactiver dans votre profil ou dans les réglages de votre téléphone ; vous ne serez alors plus prévenu automatiquement.</p>

    <h2>9. Disponibilité et responsabilité</h2>
    <p>L'Éditeur fait ses meilleurs efforts pour assurer l'accès au service 24 h/24, mais ne peut garantir une disponibilité sans interruption : maintenance, mises à jour, pannes du réseau ou de l'hébergeur. Le service est fourni « en l'état ».</p>
    <p>La responsabilité de l'Éditeur ne saurait être engagée pour : la qualité, les délais ou la perte d'articles confiés à un pressing ; les informations saisies par un pressing ; l'absence de réception d'une notification ; ou un dommage résultant d'une mauvaise utilisation du service ou d'un accès non autorisé dû à la négligence de l'utilisateur.</p>

    <h2>10. Propriété intellectuelle</h2>
    <p>Le service, son logiciel, sa marque et ses contenus appartiennent à l'Éditeur. Les utilisateurs bénéficient d'un droit d'utilisation personnel, non exclusif et non transférable, limité à la durée de leur utilisation du service.</p>

    <h2>11. Suspension et suppression du compte</h2>
    <p>Vous pouvez demander la suppression de votre compte à tout moment auprès du support (appel ou WhatsApp au {{ config('presslink.support.phone_display') }}). L'Éditeur peut suspendre ou supprimer un compte en cas de manquement aux présentes CGU, après avertissement lorsque c'est possible.</p>

    <h2>12. Données personnelles</h2>
    <p>Le traitement des données est détaillé dans la <a href="{{ route('site.privacy') }}">politique de confidentialité</a>.</p>

    <h2>13. Modification des CGU</h2>
    <p>Les CGU peuvent évoluer, notamment avec les nouvelles fonctionnalités. La version en vigueur est celle publiée sur cette page ; en cas de changement important, les utilisateurs en sont informés dans l'application.</p>

    <h2>14. Droit applicable et litiges</h2>
    <p>Les présentes CGU sont soumises au droit ivoirien. En cas de différend, les parties recherchent d'abord une solution amiable en contactant le support. À défaut, les tribunaux compétents d'Abidjan seront saisis.</p>
</x-site.legal>
