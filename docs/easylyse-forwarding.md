# Easylyse forwarding

SEO Tracking Bundle contains an optional compatibility integration that
forwards successful tracking and exit events to Easylyse. It is disabled by
default and is independent from local Doctrine statistics.

Prefer an application-owned asynchronous event subscriber for a new external
analytics integration. The built-in adapter performs synchronous HTTP calls on
the public tracking request path and exists to support the historical
Easylyse contract.

## Enable deliberately

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    easylyse_enabled: true
    easylyse_api_key: '%env(EASYLYSE_API_KEY)%'
    easylyse_api_page_call_endpoint: 'https://www.easylyse.fr/fr/api/seo/hit'
    easylyse_api_page_exit_endpoint: 'https://www.easylyse.fr/fr/api/seo/exit'
    easylyse_timeout: 5
```

Forwarding occurs only when the integration is enabled and the API key and
relevant endpoint are non-empty. The key is sent in the `X-Api-Key` header.
HTTP errors and transport exceptions are logged and do not roll back the local
hit.

`auto_send` remains an accepted historical configuration key in 1.7 but is not
read by the listener. Do not use it as an enable/disable switch;
`easylyse_enabled` controls the integration.

## Data sent

The page-call event can include:

- page-call and hit identifiers;
- grouping and observed URLs;
- route, route arguments and UTM values;
- first and last call timestamps;
- parent-hit identifier and delay;
- page title and type;
- referrer and stored User-Agent;
- anonymized IP;
- language and screen dimensions;
- bot booleans and hit timestamps.

The exit event sends the page-call identifier, hit identifier and exit
timestamp.

This is a broader payload than the privacy-minimized CSV exporter. Before
enabling it, verify the controller relationship, destination retention,
international transfer position, access controls, incident handling and
privacy information for the consuming application. Never place the API key in
the repository; use a secret environment value.

## Operational behavior

- Requests are synchronous, so the configured timeout directly affects the
  tracking and exit endpoint latency.
- A non-2xx response is logged as a warning.
- A transport or listener exception is logged as an error and local collection
  remains successful.
- No retry queue, circuit breaker or delivery guarantee is provided.
- Custom application-defined dimensions are not included in the historical
  forwarding payload.

For reliable or high-volume delivery, subscribe to `PageCallTrackedEvent` and
`PageCallExitEvent`, enqueue a bounded application message and perform remote
delivery in a supervised worker.
