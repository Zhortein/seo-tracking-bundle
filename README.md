# seo-tracking-bundle
Symfony bundle to track page views, UTM campaigns and basic engagement, with optional SEO insights integration.


## 🔁 Listen to PageCallTrackedEvent

The bundle dispatches an event every time a tracked visit is recorded. You can listen to this event in your app using an EventListener
like this example.

```php
use ZhorTein\SeoTrackingBundle\Event\PageCallTrackedEvent;

class MyCustomListener
{
    public function __invoke(PageCallTrackedEvent $event): void
    {
        $pageCall = $event->pageCall;
        $hit = $event->pageCallHit;

        // Example: export to your own system
        // or send it to a queue, or just log it
    }
}
```

