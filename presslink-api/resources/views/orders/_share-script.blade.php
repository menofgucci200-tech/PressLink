{{--
    presslinkShare({ url, text, file, phone }) : partage d'un reçu.
    1. Sur téléphone, le PDF lui-même (WhatsApp, e-mail…) s'il a pu être
       préparé à l'avance (presslinkShare.prefetch, au survol ou au toucher) ;
    2. sinon le lien du reçu, par la feuille de partage du téléphone ;
    3. sinon (ordinateur) WhatsApp, vers le numéro du client.
--}}
<script>
    window.presslinkShare = window.presslinkShare || (function () {
        const ready = {};
        const pending = {};

        function prefetch(opts) {
            if (!opts || !opts.file || pending[opts.url] || !navigator.canShare) return;
            pending[opts.url] = fetch(opts.url)
                .then((response) => (response.ok ? response.blob() : null))
                .then((blob) => { if (blob) ready[opts.url] = new File([blob], opts.file, { type: 'application/pdf' }); })
                .catch(() => {});
        }

        async function share(opts) {
            const file = ready[opts.url];
            try {
                if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: opts.text, text: opts.text });
                    return;
                }
                if (navigator.share) {
                    await navigator.share({ title: opts.text, text: opts.text, url: opts.url });
                    return;
                }
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
            const digits = String(opts.phone || '').replace(/\D/g, '');
            window.open('https://wa.me/' + digits + '?text=' + encodeURIComponent(opts.text + ' : ' + opts.url), '_blank', 'noopener');
        }

        share.prefetch = prefetch;
        return share;
    })();
</script>
