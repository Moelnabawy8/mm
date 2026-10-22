{{-- On a phone, a wa.me link stops on WhatsApp's "Open app" page instead of opening the
     app. Open the app directly there (Android asks which one when both WhatsApp and
     WhatsApp Business are installed). Computers keep the wa.me link. --}}
<script>
    (function () {
        if (!/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            return;
        }
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href*="wa.me/"], a[href*="api.whatsapp.com/send"]');
            if (!a) {
                return;
            }
            var url;
            try {
                url = new URL(a.href);
            } catch (err) {
                return;
            }
            var phone = (url.hostname.replace(/^www\./, '') === 'wa.me' ? url.pathname : (url.searchParams.get('phone') || '')).replace(/\D/g, '');
            if (!phone) {
                return;
            }
            e.preventDefault();
            var text = url.searchParams.get('text');
            var left = false;
            document.addEventListener('visibilitychange', function () { left = true; }, { once: true });
            window.location.href = 'whatsapp://send?phone=' + phone + (text ? '&text=' + encodeURIComponent(text) : '');
            // No WhatsApp app on the phone: the page is still showing, so use the web link.
            setTimeout(function () {
                if (!left && !document.hidden) {
                    window.location.href = a.href;
                }
            }, 1500);
        }, true);
    })();
</script>
