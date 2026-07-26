# Accessible statistics presentation

Version 1.7 keeps statistics calculation independent from presentation and adds two translated, overrideable themes:

- `bootstrap5`, the backward-compatible default;
- `html5`, a framework-neutral semantic theme with no CSS or JavaScript dependency.

Set `theme: none` to keep only the provider, DTO and Twig functions.

## Select a theme

Bootstrap 5 remains the default:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: bootstrap5
```

Use the standalone HTML theme:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: html5
```

Or disable bundled presentation:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: none
```

The `html5` template emits semantic sections, description lists, ordered rankings, native `<progress>` elements, `<time>` values and a complete trend table. It does not load a stylesheet, script, web component or remote asset. Applications can style its elements and `data-seo-tracking-statistics` hook normally.

The Bootstrap template only emits Bootstrap 5 class names and inline proportional dimensions. It still does not install Bootstrap or JavaScript.

## Visual and tabular data

Both themes render daily evolution twice from the same `TrendPoint` values:

- a lightweight proportional visual;
- a complete table with date, page calls, human hits and robot hits.

The HTML5 visual uses native progress elements. The Bootstrap theme uses labelled bars and retains a visually hidden table caption. Empty and zero-count reports use a safe maximum of one, so generated markup never contains a division by zero, `NaN` or an infinite percentage.

Rankings also include proportional indicators relative to the highest displayed value. The textual label and exact count remain present; the indicator is supplementary rather than the only way to read a value.

These visuals describe hit counts only. They do not introduce or imply unique visitors, sessions, devices or identities.

## English and French labels

Bundled labels live in the `seo_tracking` translation domain:

```text
translations/seo_tracking.en.yaml
translations/seo_tracking.fr.yaml
```

Symfony chooses the active locale using the application's normal request and translator configuration. Override individual labels in the host application with the same domain and keys, for example:

```yaml
# translations/seo_tracking.fr.yaml
statistics:
    page_calls: 'Pages consultées'
```

Custom dimension names and tracked labels remain application data. Twig auto-escaping applies to those values in both bundled themes.

## Override templates and blocks

Override the complete default theme using Symfony's bundle convention:

```text
templates/bundles/ZhorteinSeoTrackingBundle/statistics/bootstrap5/report.html.twig
templates/bundles/ZhorteinSeoTrackingBundle/statistics/html5/report.html.twig
```

Both themes retain the same primary blocks:

- `summary`
- `trend`
- `rankings`
- `dimensions`

An application can also set `statistics.template` to any Twig file; an explicit template continues to take precedence over the selected theme.

Every template receives one `StatisticsReport` variable named `report`. Keep the daily table or an equivalent accessible representation when replacing the supplied trend visual.
