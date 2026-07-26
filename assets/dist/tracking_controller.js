import { Controller } from '@hotwired/stimulus';

const DEFAULT_TRACKING_URL = '/zhortein/seo-tracking/page-call/track';
const DEFAULT_EXIT_URL = '/zhortein/seo-tracking/page-call/exit';
const HIT_STORAGE_KEY = 'page-call-hit-id';

export default class extends Controller {
    static values = {
        type: { type: String, default: 'generic' },
        route: { type: String, default: '' },
        routeArgs: { type: Object, default: {} },
        canonicalUrl: { type: String, default: '' },
        trackingUrl: { type: String, default: DEFAULT_TRACKING_URL },
        exitUrl: { type: String, default: DEFAULT_EXIT_URL },
    };

    activeHitId = null;
    closeRequested = false;
    connected = false;
    lastTrackedUrl = null;
    listenersAttached = false;
    trackPromise = null;
    closedHitIds = new Set();

    connect() {
        this.connected = true;
        this.attachListeners();
        void this.trackCurrentPage();
    }

    disconnect() {
        this.connected = false;
        this.detachListeners();
        this.closeCurrentHit();
    }

    attachListeners() {
        if (this.listenersAttached) {
            return;
        }

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        document.addEventListener('turbo:before-visit', this.onTurboBeforePageChange);
        document.addEventListener('turbo:before-render', this.onTurboBeforePageChange);
        document.addEventListener('turbo:before-cache', this.onTurboBeforePageChange);
        document.addEventListener('turbo:load', this.onTurboLoad);
        window.addEventListener('pagehide', this.onPageHide);
        this.listenersAttached = true;
    }

    detachListeners() {
        if (!this.listenersAttached) {
            return;
        }

        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        document.removeEventListener('turbo:before-visit', this.onTurboBeforePageChange);
        document.removeEventListener('turbo:before-render', this.onTurboBeforePageChange);
        document.removeEventListener('turbo:before-cache', this.onTurboBeforePageChange);
        document.removeEventListener('turbo:load', this.onTurboLoad);
        window.removeEventListener('pagehide', this.onPageHide);
        this.listenersAttached = false;
    }

    async trackCurrentPage() {
        const url = window.location.href;
        if (this.trackPromise || (this.activeHitId && this.lastTrackedUrl === url)) {
            return this.trackPromise;
        }

        if ('function' !== typeof fetch) {
            console.warn('SEO tracking is unavailable because fetch() is not supported.');
            return null;
        }

        if (this.activeHitId) {
            this.closeCurrentHit();
        }

        this.closeRequested = false;
        const params = new URLSearchParams(window.location.search);
        const previousHitId = sessionStorage.getItem(HIT_STORAGE_KEY);
        const payload = {
            url,
            canonicalUrl: this.resolveCanonicalUrl(),
            route: this.routeValue || null,
            routeArgs: this.routeArgsValue ?? null,
            campaign: params.get('utm_campaign'),
            medium: params.get('utm_medium'),
            source: params.get('utm_source'),
            term: params.get('utm_term'),
            content: params.get('utm_content'),
            language: navigator.language || null,
            screen: {
                width: window.screen?.width ?? null,
                height: window.screen?.height ?? null,
            },
            parentHitId: previousHitId,
            title: document.title || null,
            type: this.typeValue || 'generic',
        };

        this.trackPromise = fetch(this.trackingUrlValue || DEFAULT_TRACKING_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Tracking endpoint returned HTTP ${response.status}.`);
                }

                return response.json();
            })
            .then((data) => {
                if (null === data.hitId || undefined === data.hitId) {
                    return null;
                }

                this.activeHitId = String(data.hitId);
                this.lastTrackedUrl = url;
                sessionStorage.setItem(HIT_STORAGE_KEY, this.activeHitId);

                if (this.closeRequested || !this.connected) {
                    this.closeCurrentHit();
                }

                return this.activeHitId;
            })
            .catch((error) => {
                console.warn('SEO tracking request failed.', error);

                return null;
            })
            .finally(() => {
                this.trackPromise = null;
            });

        return this.trackPromise;
    }

    resolveCanonicalUrl() {
        const configuredUrl = this.canonicalUrlValue?.trim();
        if (configuredUrl) {
            return configuredUrl;
        }

        return document.querySelector('link[rel="canonical"]')?.href || null;
    }

    closeCurrentHit() {
        if (!this.activeHitId) {
            this.closeRequested = null !== this.trackPromise;

            return;
        }

        const hitId = this.activeHitId;
        this.activeHitId = null;
        this.closeRequested = false;

        if (this.closedHitIds.has(hitId)) {
            return;
        }

        this.closedHitIds.add(hitId);
        const body = JSON.stringify({ hitId });
        let beaconSent = false;

        if ('function' === typeof navigator.sendBeacon && 'function' === typeof Blob) {
            try {
                beaconSent = navigator.sendBeacon(
                    this.exitUrlValue || DEFAULT_EXIT_URL,
                    new Blob([body], { type: 'application/json' }),
                );
            } catch (error) {
                console.warn('SEO tracking beacon failed.', error);
            }
        }

        if (!beaconSent && 'function' === typeof fetch) {
            void fetch(this.exitUrlValue || DEFAULT_EXIT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                keepalive: true,
                body,
            }).catch((error) => {
                console.warn('SEO tracking exit request failed.', error);
            });
        }
    }

    onVisibilityChange = () => {
        if ('hidden' === document.visibilityState) {
            this.closeCurrentHit();
        } else if ('visible' === document.visibilityState && this.connected) {
            void this.trackCurrentPage();
        }
    };

    onTurboBeforePageChange = () => {
        this.closeCurrentHit();
    };

    onTurboLoad = () => {
        if (this.connected) {
            void this.trackCurrentPage();
        }
    };

    onPageHide = () => {
        this.closeCurrentHit();
    };
}
