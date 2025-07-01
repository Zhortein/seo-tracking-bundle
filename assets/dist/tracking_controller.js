import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        type: { type: String, default: 'generic' },
        route: { type: String, default: null },
        routeArgs: { type: Array, default: null },
    }

    connect() {
        try {
            const url = window.location.href;
            const params = new URLSearchParams(window.location.search);
            const previousHitId = sessionStorage.getItem('page-call-hit-id');

            const payload = {
                url: url,
                route: this.routeValue || null,
                routeArgs: this.routeArgsValue ? JSON.parse(this.routeArgsValue) : null,
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
                parentHitId: previousHitId ? parseInt(previousHitId) : null,
                title: document.title,
                type: this.typeValue,
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

            document.addEventListener('visibilitychange', this.onVisibilityChange);
        } catch (e) {+
            console.warn('Tracking error', e);
        }
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
    }

    onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            const hitId = sessionStorage.getItem('page-call-hit-id');
            if (hitId) {
                navigator.sendBeacon(
                    '/zhortein/seo-tracking/page-call/exit',
                    new Blob([JSON.stringify({ hitId })], { type: 'application/json' })
                );
            }
        }
    };
}
