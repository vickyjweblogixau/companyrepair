/**
 * CRS Theme — theme.js
 * Initialises Swiper carousel and any other front-end interactions.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Blog / Article Swiper ──────────────────────────────────────────
    if (document.querySelector('.blog-swiper')) {
        new Swiper('.blog-swiper', {
            slidesPerView: 1,
            spaceBetween: 16,
            grabCursor: true,
            keyboard: { enabled: true, onlyInViewport: true },
            navigation: {
                prevEl: '#blogPrev',
                nextEl: '#blogNext',
            },
            breakpoints: {
                576:  { slidesPerView: 2, spaceBetween: 16 },
                768:  { slidesPerView: 3, spaceBetween: 18 },
                992:  { slidesPerView: 4, spaceBetween: 20 },
                1200: { slidesPerView: 5, spaceBetween: 20 },
            },
        });
    }

    // ── Suburb AJAX search (listing pages) ────────────────────────────
    const suburbInput = document.getElementById('crs-suburb-input');
    if (suburbInput) {
        let timeout = null;
        suburbInput.addEventListener('input', function () {
            clearTimeout(timeout);
            const q = this.value.trim();
            if (q.length < 2) return;
            timeout = setTimeout(function () {
                fetch(crsAjax.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'crs_suburb_search',
                        nonce:  crsAjax.nonce,
                        q:      q,
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.length) {
                        // TODO: render autocomplete dropdown
                        console.log('Suburbs:', data.data);
                    }
                });
            }, 300);
        });
    }

});
