<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;

class Configuration implements ConfigurationInterface
{
    /**
     * Config tree.
     *
     *  zhortein_seo_tracker:
     *      page_call_class: Zhortein\SeoTrackingBundle\Entity\PageCall
     *      page_call_hit_class: Zhortein\SeoTrackingBundle\Entity\PageCallHit
     *      isatis_concept_enabled: false
     *      isatis_concept_api_key: ''
     *      isatis_concept_api_endpoint: ''
     *      auto_send: false
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zhortein_seo_tracker');

        $treeBuilder->getRootNode()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('page_call_class')
                        ->defaultValue(PageCall::class)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('page_call_hit_class')
                        ->defaultValue(PageCallHit::class)
                        ->cannotBeEmpty()
                    ->end()
                    ->booleanNode('isatis_concept_enabled')->defaultValue(false)->end()
                    ->scalarNode('isatis_concept_api_key')->defaultValue('')->end()
                    ->scalarNode('isatis_concept_api_endpoint')->defaultValue('')->end()
                    ->booleanNode('auto_send')->defaultValue(false)->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
