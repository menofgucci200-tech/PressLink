{{flutter_js}}
{{flutter_build_config}}

// Pas de service worker Flutter (déprécié, il se désinscrit tout seul) :
// l'ouverture hors connexion est assurée par hors-connexion-sw.js, enregistré
// dans index.html. Ne pas modifier l'appel de chargement ci-dessous :
// deploy/version-assets.sh y ajoute le dossier des ressources de la version.
_flutter.loader.load({
});
