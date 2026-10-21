{{--
    Espace pressing installable sur PC (Chrome, Edge) : manifeste, service
    worker (public/espace-sw.js : page « hors connexion » au lieu de l'erreur
    du navigateur) et `window.presslinkInstall` pour le bouton « Installer ».
--}}
<link rel="manifest" href="{{ asset('manifest-espace.json') }}">
<meta name="theme-color" content="#1e3a8a">
<script>
    (function () {
        if (window.presslinkInstall) return;
        var deferred = null;
        function changed() { window.dispatchEvent(new Event('presslink-install')); }
        window.presslinkInstall = {
            available: function () { return deferred !== null; },
            prompt: function () {
                if (!deferred) return;
                var event = deferred;
                deferred = null;
                event.prompt();
                event.userChoice.finally(changed);
            }
        };
        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferred = event;
            changed();
        });
        window.addEventListener('appinstalled', function () { deferred = null; changed(); });
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('{{ asset('espace-sw.js') }}', { scope: '/' }).catch(function () {});
            });
        }
    })();
</script>
