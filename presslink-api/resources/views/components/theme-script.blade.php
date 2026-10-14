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

        function remember(mode) {
            try {
                if (mode === 'system') {
                    localStorage.removeItem(storageKey);
                } else {
                    localStorage.setItem(storageKey, mode);
                }
            } catch (e) {}
        }

        function switchTo(theme) {
            var root = document.documentElement;
            root.classList.add('theme-switching');
            apply(theme);
            setTimeout(function () {
                root.classList.remove('theme-switching');
            }, 300);
            window.dispatchEvent(new CustomEvent('presslink-theme-changed'));
        }

        window.PressLinkTheme = {
            current: function () {
                return document.documentElement.getAttribute('data-theme') || 'light';
            },
            /** Choix enregistré : 'light', 'dark' ou 'system' (réglage de l'appareil). */
            mode: function () {
                return stored() || 'system';
            },
            set: function (mode) {
                remember(mode);
                switchTo(preferred());
            },
            toggle: function () {
                var next = this.current() === 'dark' ? 'light' : 'dark';
                remember(next);
                switchTo(next);
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
