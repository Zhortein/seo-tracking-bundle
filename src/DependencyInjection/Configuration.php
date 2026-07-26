<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zhortein_seo_tracking');

        $treeBuilder->getRootNode()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('page_call_class')
                        ->defaultValue(PageCall::class)
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn (string $class): bool => !is_a($class, PageCallInterface::class, true))
                            ->thenInvalid(sprintf('The configured page call class must implement %s.', PageCallInterface::class))
                        ->end()
                    ->end()
                    ->scalarNode('page_call_hit_class')
                        ->defaultValue(PageCallHit::class)
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn (string $class): bool => !is_a($class, PageCallHitInterface::class, true))
                            ->thenInvalid(sprintf('The configured page call hit class must implement %s.', PageCallHitInterface::class))
                        ->end()
                    ->end()
                    ->arrayNode('anonymization')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('ipv4_prefix')->min(0)->max(32)->defaultValue(24)->end()
                            ->integerNode('ipv6_prefix')->min(0)->max(128)->defaultValue(64)->end()
                        ->end()
                    ->end()
                    ->scalarNode('tracking_url')->defaultNull()->end()
                    ->scalarNode('exit_url')->defaultNull()->end()
                    ->arrayNode('statistics')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('theme')
                                ->values(['bootstrap5', 'html5', 'none'])
                                ->defaultValue('bootstrap5')
                            ->end()
                            ->scalarNode('template')->defaultNull()->end()
                            ->arrayNode('cache')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('pool')
                                        ->defaultNull()
                                        ->validate()
                                            ->ifTrue(static fn (?string $service): bool => null !== $service && '' === trim($service))
                                            ->thenInvalid('The statistics cache pool service ID cannot be empty.')
                                        ->end()
                                    ->end()
                                    ->integerNode('ttl')->defaultValue(0)->min(0)->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static function (array $cache): bool {
                                        $pool = $cache['pool'] ?? null;
                                        $ttl = $cache['ttl'] ?? null;

                                        return (null === $pool && 0 !== $ttl)
                                            || (null !== $pool && (!is_int($ttl) || $ttl < 1));
                                    })
                                    ->thenInvalid('Configure both a statistics cache pool and a positive TTL, or disable both.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('retention')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('days')->defaultNull()->min(1)->end()
                            ->integerNode('batch_size')->defaultValue(500)->min(1)->end()
                            ->booleanNode('remove_empty_page_calls')->defaultFalse()->end()
                        ->end()
                    ->end()
                    ->arrayNode('consent')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('grant_event')
                                ->defaultValue('seo-tracking:consent-granted')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('revoke_event')
                                ->defaultValue('seo-tracking:consent-revoked')
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('rate_limiter')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('creation_limiter')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (?string $service): bool => null !== $service && '' === trim($service))
                                    ->thenInvalid('The creation limiter service ID cannot be empty.')
                                ->end()
                            ->end()
                            ->scalarNode('closure_limiter')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (?string $service): bool => null !== $service && '' === trim($service))
                                    ->thenInvalid('The closure limiter service ID cannot be empty.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->booleanNode('easylyse_enabled')->defaultValue(false)->end()
                    ->scalarNode('easylyse_api_key')->defaultValue('')->end()
                    ->scalarNode('easylyse_api_page_call_endpoint')->defaultValue('https://www.easylyse.fr/fr/api/seo/hit')->end()
                    ->scalarNode('easylyse_api_page_exit_endpoint')->defaultValue('https://www.easylyse.fr/fr/api/seo/exit')->end()
                    ->integerNode('easylyse_timeout')->defaultValue(300)->end()
                    ->booleanNode('auto_send')->defaultValue(false)->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
