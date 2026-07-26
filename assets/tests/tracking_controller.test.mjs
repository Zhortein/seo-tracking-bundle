import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const controllerPath = fileURLToPath(new URL('../dist/tracking_controller.js', import.meta.url));

class FakeEventTarget {
    listeners = new Map();

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? new Set();
        listeners.add(listener);
        this.listeners.set(type, listeners);
    }

    removeEventListener(type, listener) {
        this.listeners.get(type)?.delete(listener);
    }

    dispatch(type) {
        for (const listener of this.listeners.get(type) ?? []) {
            listener({ type });
        }
    }

    listenerCount(type) {
        return this.listeners.get(type)?.size ?? 0;
    }
}

function createHarness({ canonicalHref = null, deferredTrack = false, sendBeacon = true } = {}) {
    const documentTarget = new FakeEventTarget();
    const windowTarget = new FakeEventTarget();
    const storage = new Map();
    const fetchCalls = [];
    const beaconCalls = [];
    let nextHitId = 1;
    let resolveDeferredTrack;

    const document = Object.assign(documentTarget, {
        title: 'Tracked page',
        visibilityState: 'visible',
        querySelector: (selector) => 'link[rel="canonical"]' === selector && canonicalHref
            ? { href: canonicalHref }
            : null,
    });
    const window = Object.assign(windowTarget, {
        location: {
            href: 'https://example.test/first?utm_source=newsletter',
            search: '?utm_source=newsletter',
        },
        screen: { width: 1280, height: 720 },
    });
    const navigator = {
        language: 'fr-FR',
        sendBeacon: sendBeacon
            ? (url, body) => {
                beaconCalls.push({ url, body });

                return true;
            }
            : undefined,
    };
    const sessionStorage = {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, String(value)),
    };
    const fetch = (url, options) => {
        fetchCalls.push({ url, options });

        if (options.keepalive) {
            return Promise.resolve({ ok: true, status: 200, json: async () => ({ status: 'ok' }) });
        }

        if (deferredTrack && 1 === nextHitId) {
            return new Promise((resolve) => {
                resolveDeferredTrack = () => resolve({
                    ok: true,
                    status: 200,
                    json: async () => ({ hitId: nextHitId++ }),
                });
            });
        }

        return Promise.resolve({
            ok: true,
            status: 200,
            json: async () => ({ hitId: nextHitId++ }),
        });
    };

    const source = readFileSync(controllerPath, 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}')
        .replace('export default class extends Controller', 'class TrackingController extends Controller')
        .concat('\nglobalThis.TrackingController = TrackingController;\n');
    const context = vm.createContext({
        Blob,
        URLSearchParams,
        console,
        document,
        fetch,
        navigator,
        sessionStorage,
        window,
    });
    new vm.Script(source, { filename: controllerPath }).runInContext(context);

    const controller = new context.TrackingController();
    Object.assign(controller, {
        canonicalUrlValue: '',
        exitUrlValue: '/custom/exit',
        routeArgsValue: { slug: 'first' },
        routeValue: 'page_show',
        trackingUrlValue: '/custom/track',
        typeValue: 'article',
    });

    return {
        beaconCalls,
        controller,
        document,
        fetchCalls,
        resolveDeferredTrack: () => resolveDeferredTrack(),
        sessionStorage,
        window,
    };
}

test('the distributed Stimulus controller is valid JavaScript', () => {
    assert.doesNotThrow(() => {
        execFileSync(process.execPath, ['--check', controllerPath], { stdio: 'pipe' });
    });
});

test('configurable endpoints, canonical URL and listeners work without duplication', async () => {
    const harness = createHarness();
    harness.controller.canonicalUrlValue = 'https://example.test/canonical';

    harness.controller.connect();
    harness.controller.connect();
    await harness.controller.trackPromise;

    assert.equal(harness.fetchCalls.length, 1);
    assert.equal(harness.fetchCalls[0].url, '/custom/track');
    const payload = JSON.parse(harness.fetchCalls[0].options.body);
    assert.equal(payload.canonicalUrl, 'https://example.test/canonical');
    assert.equal(payload.source, 'newsletter');
    assert.equal(harness.document.listenerCount('visibilitychange'), 1);
    assert.equal(harness.document.listenerCount('turbo:load'), 1);

    harness.document.dispatch('turbo:before-visit');
    harness.document.dispatch('turbo:before-render');
    harness.controller.disconnect();

    assert.equal(harness.beaconCalls.length, 1);
    assert.equal(harness.beaconCalls[0].url, '/custom/exit');
    assert.equal(harness.document.listenerCount('visibilitychange'), 0);
    assert.equal(harness.document.listenerCount('turbo:load'), 0);
});

test('Turbo page changes close the current hit and track the next page once', async () => {
    const harness = createHarness({ canonicalHref: 'https://example.test/first' });

    harness.controller.connect();
    await harness.controller.trackPromise;
    harness.document.dispatch('turbo:before-visit');
    harness.window.location.href = 'https://example.test/second';
    harness.window.location.search = '';
    harness.document.dispatch('turbo:load');
    await harness.controller.trackPromise;

    const trackingCalls = harness.fetchCalls.filter((call) => !call.options.keepalive);
    assert.equal(trackingCalls.length, 2);
    assert.equal(JSON.parse(trackingCalls[0].options.body).canonicalUrl, 'https://example.test/first');
    assert.equal(JSON.parse(trackingCalls[1].options.body).parentHitId, '1');
    assert.equal(harness.beaconCalls.length, 1);
});

test('fetch keepalive closes hits when sendBeacon is unavailable', async () => {
    const harness = createHarness({ sendBeacon: false });

    harness.controller.connect();
    await harness.controller.trackPromise;
    harness.document.visibilityState = 'hidden';
    harness.document.dispatch('visibilitychange');
    await Promise.resolve();

    const exitCall = harness.fetchCalls.find((call) => call.options.keepalive);
    assert.ok(exitCall);
    assert.equal(exitCall.url, '/custom/exit');
    assert.deepEqual(JSON.parse(exitCall.options.body), { hitId: '1' });

    harness.document.visibilityState = 'visible';
    harness.document.dispatch('visibilitychange');
    await harness.controller.trackPromise;
    assert.equal(harness.fetchCalls.filter((call) => !call.options.keepalive).length, 2);
});

test('a page hidden before the tracking response is still closed', async () => {
    const harness = createHarness({ deferredTrack: true });

    harness.controller.connect();
    harness.document.visibilityState = 'hidden';
    harness.document.dispatch('visibilitychange');
    harness.resolveDeferredTrack();
    await harness.controller.trackPromise;

    assert.equal(harness.beaconCalls.length, 1);
});
