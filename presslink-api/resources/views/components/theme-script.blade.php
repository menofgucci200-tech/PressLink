{{-- Mode jour / nuit — à placer dans le <head>, après la balise
     theme-color : le thème est appliqué avant le premier affichage (pas de
     flash blanc). Choix mémorisé par navigateur ; sans choix, on suit le
     réglage du téléphone ou de l'ordinateur. Livewire réécrit les attributs
     de <html> à chaque wire:navigate : le thème est réappliqué juste après. --}}
<script>
    (function () {
        if (window.PressLinkTheme) {
            return;
        }

        var storageKey = 'presslink-theme';
        var systemDark = window.matchMedia('(prefers-color-scheme: dark)');
        var themeColor = document.querySelector('meta[name="theme-color"]');
        var lightThemeColor = themeColor ? themeColor.content : null;

        function stored() {
            try {
                var value = localStorage.getItem(storageKey);

                return value === 'dark' || value === 'light' ? value : null;
            } catch (e) {
                return null;
            }
        }

        function preferred() {
            return stored() || (systemDark.matches ? 'dark' : 'light');
        }

        function apply(theme) {
            var root = document.documentElement;
            root.setAttribute('data-theme', theme);

            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta && lightThemeColor) {
                meta.content = theme === 'dark' ? '#0b1220' : lightThemeColor;
            }
        }

        window.PressLinkTheme = {
            current: function () {
                return document.documentElement.getAttribute('data-theme') || 'light';
            },
            toggle: function () {
                var next = this.current() === 'dark' ? 'light' : 'dark';
                var root = document.documentElement;

                try {
                    localStorage.setItem(storageKey, next);
                } catch (e) {}

                root.classList.add('theme-switching');
                apply(next);
                setTimeout(function () {
                    root.classList.remove('theme-switching');
                }, 300);
            },
        };

        apply(preferred());

        systemDark.addEventListener('change', function () {
            if (!stored()) {
                apply(preferred());
            }
        });

        document.addEventListener('livewire:navigating', function (event) {
            event.detail.onSwap(function () {
                apply(preferred());
            });
        });
        document.addEventListener('livewire:navigated', function () {
            apply(preferred());
        });
    })();
</script>
