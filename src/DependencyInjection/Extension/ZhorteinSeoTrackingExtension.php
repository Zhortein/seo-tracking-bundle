<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection\Extension;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zhortein\SeoTrackingBundle\DependencyInjection\Configuration;
use Zhortein\SeoTrackingBundle\DTO\SeoTrackingOptions;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Statistics\Cache\Psr6StatisticsReportCache;
use Zhortein\SeoTrackingBundle\Statistics\Cache\StatisticsReportCacheInterface;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\SymfonyTrackingRateLimiter;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimiterInterface;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimitKeyResolverInterface;

class ZhorteinSeoTrackingExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $pageCallClass = $config['page_call_class'] ?? null;
        $pageCallHitClass = $config['page_call_hit_class'] ?? null;
        $anonymization = $config['anonymization'] ?? null;
        if (!is_string($pageCallClass) || !is_string($pageCallHitClass) || !is_array($anonymization)) {
            throw new \LogicException('Invalid SEO tracking entity configuration.');
        }

        $ipv4Prefix = $anonymization['ipv4_prefix'] ?? null;
        $ipv6Prefix = $anonymization['ipv6_prefix'] ?? null;
        if (!is_int($ipv4Prefix) || !is_int($ipv6Prefix)) {
            throw new \LogicException('Invalid SEO tracking anonymization configuration.');
        }

        $container->setParameter('zhortein_seo_tracking.page_call_class', $pageCallClass);
        $container->setParameter('zhortein_seo_tracking.page_call_hit_class', $pageCallHitClass);
        $container->setParameter('zhortein_seo_tracking.anonymization.ipv4_prefix', $ipv4Prefix);
        $container->setParameter('zhortein_seo_tracking.anonymization.ipv6_prefix', $ipv6Prefix);
        $container->setParameter('zhortein_seo_tracking.tracking_url', $this->optionalString($config['tracking_url'] ?? null, 'tracking_url'));
        $container->setParameter('zhortein_seo_tracking.exit_url', $this->optionalString($config['exit_url'] ?? null, 'exit_url'));
        $container->setParameter('zhortein_seo_tracking.statistics.template', $this->statisticsTemplate($config['statistics'] ?? null));
        $statisticsCache = $this->statisticsCache($config['statistics'] ?? null);
        $container->setParameter('zhortein_seo_tracking.statistics.cache_ttl', $statisticsCache['ttl']);
        $this->configureStatisticsCache($container, $statisticsCache['pool']);
        $retention = $this->retention($config['retention'] ?? null);
        $container->setParameter('zhortein_seo_tracking.retention.days', $retention['days']);
        $container->setParameter('zhortein_seo_tracking.retention.batch_size', $retention['batch_size']);
        $container->setParameter('zhortein_seo_tracking.retention.remove_empty_page_calls', $retention['remove_empty_page_calls']);
        $consent = $this->consent($config['consent'] ?? null);
        $container->setParameter('zhortein_seo_tracking.consent.grant_event', $consent['grant_event']);
        $container->setParameter('zhortein_seo_tracking.consent.revoke_event', $consent['revoke_event']);
        $this->configureRateLimiter($container, $config['rate_limiter'] ?? null);

        $def = new Definition(SeoTrackingOptions::class, [
            $config['easylyse_api_page_call_endpoint'] ?? null,
            $config['easylyse_api_page_exit_endpoint'] ?? null,
            $config['easylyse_api_key'] ?? null,
            $config['easylyse_enabled'] ?? false,
            $config['easylyse_timeout'] ?? 300,
        ]);
        $def->setPublic(false);
        $container->setDefinition(SeoTrackingOptions::class, $def);

        $this->handleBundleRoutes($container);
    }

    protected function handleBundleRoutes(ContainerBuilder $container): void
    {
        $filesystem = new Filesystem();
        /** @var string|null $projectPath */
        $projectPath = $container->getParameter('kernel.project_dir');
        $filePath = $projectPath.'/config/routes/zhortein_seo_tracking.yaml';

        if (!$filesystem->exists($filePath)) {
            $filesystem->dumpFile($filePath, <<<YAML
zhortein_seo_tracking:
    resource: '@ZhorteinSeoTrackingBundle/config/routes.yaml'
YAML);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        // ✅ Charger la config utilisateur
        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register dynamic targetEntities for Doctrine
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ZhorteinSeoTrackingBundle' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => realpath(__DIR__.'/../../Entity'),
                        'prefix' => 'Zhortein\SeoTrackingBundle\Entity',
                        'alias' => 'ZhorteinSeoTracking',
                    ],
                ],
                'resolve_target_entities' => [
                    PageCallInterface::class => $config['page_call_class'],
                    PageCallHitInterface::class => $config['page_call_hit_class'],
                ],
            ],
        ]);

        $this->configureAssetMapper($container);
    }

    private function configureAssetMapper(ContainerBuilder $container): void
    {
        if (!$this->isAssetMapperAvailable($container)) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/../../../assets/dist' => '@zhortein/seo-tracking-bundle',
                ],
            ],
        ]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        /** @var array<string, string|int|bool|float|null> $frameworkBundle */
        $frameworkBundle = $container->getParameter('kernel.bundles_metadata')['FrameworkBundle'] ?? null;

        return $frameworkBundle && is_file($frameworkBundle['path'].'/Resources/config/asset_mapper.php');
    }

    private function optionalString(mixed $value, string $option): ?string
    {
        if (null === $value || is_string($value)) {
            return $value;
        }

        throw new \LogicException(sprintf('The "%s" option must be a string or null.', $option));
    }

    private function statisticsTemplate(mixed $statistics): ?string
    {
        if (!is_array($statistics)) {
            throw new \LogicException('The "statistics" option must be an array.');
        }

        $template = $statistics['template'] ?? null;
        if (null !== $template) {
            if (!is_string($template) || '' === trim($template)) {
                throw new \LogicException('The "statistics.template" option must be a non-empty string or null.');
            }

            return $template;
        }

        return 'bootstrap5' === ($statistics['theme'] ?? null)
            ? '@ZhorteinSeoTracking/statistics/bootstrap5/report.html.twig'
            : null;
    }

    /**
     * @return array{pool: ?string, ttl: int}
     */
    private function statisticsCache(mixed $statistics): array
    {
        if (!is_array($statistics) || !is_array($statistics['cache'] ?? null)) {
            throw new \LogicException('The "statistics.cache" option must be an array.');
        }

        $cache = $statistics['cache'];
        $pool = $cache['pool'] ?? null;
        $ttl = $cache['ttl'] ?? null;
        if ((null !== $pool && !is_string($pool)) || !is_int($ttl)) {
            throw new \LogicException('Invalid statistics cache configuration.');
        }

        if ((null === $pool && 0 !== $ttl) || (null !== $pool && $ttl < 1)) {
            throw new \LogicException('Configure both a statistics cache pool and a positive TTL, or disable both.');
        }

        return ['pool' => $pool, 'ttl' => $ttl];
    }

    private function configureStatisticsCache(ContainerBuilder $container, ?string $pool): void
    {
        if (null === $pool) {
            return;
        }

        $container->setDefinition(Psr6StatisticsReportCache::class, new Definition(
            Psr6StatisticsReportCache::class,
            [new Reference($pool)],
        ));
        $container->setAlias(StatisticsReportCacheInterface::class, Psr6StatisticsReportCache::class);
    }

    /**
     * @return array{days: ?int, batch_size: int, remove_empty_page_calls: bool}
     */
    private function retention(mixed $retention): array
    {
        if (!is_array($retention)) {
            throw new \LogicException('The "retention" option must be an array.');
        }

        $days = $retention['days'] ?? null;
        $batchSize = $retention['batch_size'] ?? null;
        $removeEmptyPageCalls = $retention['remove_empty_page_calls'] ?? null;
        if ((null !== $days && !is_int($days)) || !is_int($batchSize) || !is_bool($removeEmptyPageCalls)) {
            throw new \LogicException('Invalid SEO tracking retention configuration.');
        }

        return [
            'days' => $days,
            'batch_size' => $batchSize,
            'remove_empty_page_calls' => $removeEmptyPageCalls,
        ];
    }

    /**
     * @return array{grant_event: string, revoke_event: string}
     */
    private function consent(mixed $consent): array
    {
        if (!is_array($consent)) {
            throw new \LogicException('The "consent" option must be an array.');
        }

        $grantEvent = $consent['grant_event'] ?? null;
        $revokeEvent = $consent['revoke_event'] ?? null;
        if (!is_string($grantEvent) || !is_string($revokeEvent) || $grantEvent === $revokeEvent) {
            throw new \LogicException('Consent grant and revoke events must be distinct strings.');
        }

        return [
            'grant_event' => $grantEvent,
            'revoke_event' => $revokeEvent,
        ];
    }

    private function configureRateLimiter(ContainerBuilder $container, mixed $rateLimiter): void
    {
        if (!is_array($rateLimiter)) {
            throw new \LogicException('The "rate_limiter" option must be an array.');
        }

        $creationLimiter = $rateLimiter['creation_limiter'] ?? null;
        $closureLimiter = $rateLimiter['closure_limiter'] ?? null;
        if ((null !== $creationLimiter && !is_string($creationLimiter))
            || (null !== $closureLimiter && !is_string($closureLimiter))) {
            throw new \LogicException('Tracking rate limiter service IDs must be strings or null.');
        }

        if (null === $creationLimiter && null === $closureLimiter) {
            return;
        }

        if (!interface_exists(RateLimiterFactoryInterface::class)) {
            throw new \LogicException('The optional tracking rate limiter integration requires symfony/rate-limiter.');
        }

        $container->setDefinition(SymfonyTrackingRateLimiter::class, new Definition(
            SymfonyTrackingRateLimiter::class,
            [
                null === $creationLimiter ? null : new Reference($creationLimiter),
                null === $closureLimiter ? null : new Reference($closureLimiter),
                new Reference(TrackingRateLimitKeyResolverInterface::class),
            ],
        ));
        $container->setAlias(TrackingRateLimiterInterface::class, SymfonyTrackingRateLimiter::class);
    }
}
