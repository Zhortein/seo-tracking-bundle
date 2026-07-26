# Bot classification

Bot detection is heuristic. It describes the request's User-Agent; it does not identify a person, a device or a unique visitor.

The built-in classifier deliberately uses a conservative list of common patterns and returns a typed `BotClassification`:

- `bot`: the boolean persisted by the existing `PageCall` and `PageCallHit` mappings;
- `classifier`: the service or strategy that made the decision;
- `category`: an optional broad family such as `search_engine`, `social_preview`, `monitoring` or `crawler`;
- `identifier`: an optional rule or product-family identifier.

Only `bot` is persisted by the default entities and used in grouping and statistics. The explanatory fields are available on `PageCallTrackedEvent::getBotClassification()` for immediate logging, metrics or application-specific enrichment. This keeps version 1.5 free of Doctrine mapping changes and avoids presenting heuristic labels as durable identity data.

Missing, empty and unrecognized User-Agent values are classified as human by the backward-compatible default. This means “not recognized as a robot”, not proof that the request came from a person.

## Replacing the classifier

Implement `BotClassifierInterface` and replace its alias:

```yaml
services:
    App\Analytics\BotClassifier: ~

    Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassifierInterface:
        alias: App\Analytics\BotClassifier
```

```php
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassification;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassifierInterface;

final class BotClassifier implements BotClassifierInterface
{
    public function classify(?string $userAgent): BotClassification
    {
        return BotClassification::human(self::class);
    }
}
```

The controller calls the classifier exactly once when creating a hit. Its boolean result controls the grouping key and both persisted bot flags. A custom classifier should therefore be deterministic for a given request and should not perform slow remote calls synchronously.

Classifier, category and identifier values are application-defined strings. Consumers should treat unknown values as valid and must not build exhaustive switches without a fallback.

## Existing detector replacements

`BotDetectorInterface::isBot()` remains supported. The default `BotDetectorClassifier` adapts an existing replacement to a typed result with classifier `legacy-detector` and category `unknown` for robot matches.

New integrations should implement `BotClassifierInterface`. Existing integrations do not need to change for 1.5.

## Privacy and operations

The new classification metadata is not stored by the bundle. The User-Agent itself continues to be truncated to 512 characters and stored on the hit as before. Applications that export classifications or User-Agent values remain responsible for retention, access control and privacy documentation.

Classification rules become stale as crawlers change their identifiers. Decorate or replace the service when an application needs a maintained commercial detector, allow-list, deny-list or tenant-specific policy.
