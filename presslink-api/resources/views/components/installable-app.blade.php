{{--
    Espace pressing (« PressLink Pro ») installable sur PC et sur téléphone : manifeste, service
    worker (public/espace-sw.js : page « hors connexion » au lieu de l'erreur
    du navigateur) et `window.presslinkInstall` pour les boutons « Installer » :
    invite du navigateur quand elle existe, sinon les gestes à faire (<x-install-steps />).
--}}
<link rel="manifest" href="{{ asset('manifest-espace.json') }}?v=3">
<meta name="theme-color" content="#0F172A">
{{-- « PressLink Pro » et sa tuile nuit : installé, le logiciel ne se confond pas avec l'application client (tuile bleue). --}}
<meta name="application-name" content="PressLink Pro">
<meta name="apple-mobile-web-app-title" content="PressLink Pro">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon-pro.png') }}?v=3">
<script>
    (function () {
        if (window.presslinkInstall) return;
        var deferred = null;
        var ua = navigator.userAgent || '';
        var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var android = /Android/i.test(ua);
        function changed() { window.dispatchEvent(new Event('presslink-install')); }
        window.presslinkInstall = {
            ios: ios,
            android: android,
            phone: ios || android,
            // chrome, samsung, inapp (Facebook, Instagram…), safari ou other : pour montrer le bon geste.
            browser: /FBAN|FBAV|FB_IAB|Instagram|Line\/|Snapchat|TikTok|; wv\)/.test(ua) ? 'inapp'
                : /SamsungBrowser/.test(ua) ? 'samsung'
                : /CriOS/.test(ua) ? 'chrome'
                : /FxiOS|EdgiOS|OPiOS|Firefox|OPR\/|EdgA/.test(ua) ? 'other'
                : (android && /Chrome\//.test(ua)) ? 'chrome'
                : /Safari/.test(ua) ? 'safari' : 'other',
            standalone: function () {
                return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
            },
            available: function () { return deferred !== null; },
            // Proposer l'installation : invite du navigateur, ou téléphone (gestes à montrer).
            offer: function () { return !window.presslinkInstall.standalone() && (deferred !== null || ios || android); },
            prompt: function () {
                if (!deferred) return;
                var event = deferred;
                deferred = null;
                event.prompt();
                event.userChoice.finally(changed);
            },
            start: function () {
                if (deferred) return window.presslinkInstall.prompt();
                window.dispatchEvent(new Event('presslink-install-steps'));
            },
            openInChrome: function () {
                location.href = 'intent://' + location.host + location.pathname + '#Intent;scheme=https;package=com.android.chrome;end';
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
                navigator.serviceWorker.register('{{ asset('espace-sw.js') }}', { scope: '/espace/' }).catch(function () {});
                // Ancien périmètre « / » : il englobait l'appli client (/app/) et le site.
                navigator.serviceWorker.getRegistrations().then(function (registrations) {
                    registrations.forEach(function (registration) {
                        if (registration.scope === location.origin + '/') registration.unregister();
                    });
                });
            });
        }
    })();
</script>
