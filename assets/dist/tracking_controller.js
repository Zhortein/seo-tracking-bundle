import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const url = window.location.href;

        const params = new URLSearchParams(window.location.search);

        const payload = {
            url: url,
            route: this.element.dataset.route || null,
            routeArgs: this.element.dataset.routeArgs ? JSON.parse(this.element.dataset.routeArgs) : null,
            campaign: params.get('utm_campaign'),
            medium: params.get('utm_medium'),
            source: params.get('utm_source'),
            term: params.get('utm_term'),
            content: params.get('utm_content'),
            language: navigator.language,
            screen: {
                width: window.screen.width,
                height: window.screen.height
            },
        };

        fetch('/zhortein/seo-tracking/page-call/track', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload),
        })
            .then(response => response.json())
            .then(data => {
                if (data.hitId) {
                    sessionStorage.setItem('page-call-hit-id', data.hitId);
                }
            });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                const hitId = sessionStorage.getItem('page-call-hit-id');
                if (hitId) {
                    navigator.sendBeacon('/zhortein/seo-tracking/page-call/exit', JSON.stringify({ hitId }));
                }
            }
        });
    }
}
