<?php

namespace Zhortein\SymfonyToolboxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Zhortein\SymfonyToolboxBundle\Service\Datatables\PaginatorFactory;

class Configuration implements ConfigurationInterface
{
    /**
     * Config tree.
     *
     *  zhortein_seo_tracker:
     *      isatis_concept_enabled: false
     *      isatis_concept_api_key: ''
     *      isatis_concept_api_endpoint: ''
     *      auto_send: false
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zhortein_seo_tracker');

        $treeBuilder->getRootNode()
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('isatis_concept_enabled')->defaultValue(false)->end()
                    ->scalarNode('isatis_concept_api_key')->defaultValue('')->end()
                    ->scalarNode('isatis_concept_api_endpoint')->defaultValue('')->end()
                    ->booleanNode('auto_send')->defaultValue(false)->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}