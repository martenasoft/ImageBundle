<?php

namespace MartenaSoft\ImageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('image');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->arrayPrototype()
                ->children()
                    ->arrayNode('site')
                        ->isRequired()
                        ->children()

                        ->integerNode('id')
                            ->isRequired()
                        ->end()

                        ->arrayNode('types')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('max_size')
                            ->isRequired()
                            ->end()

                        ->arrayNode('mime_types')
                            ->prototype('scalar')->end()
                        ->end()

                        ->arrayNode('sizes')
                            ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->integerNode('width')->isRequired()->end()
                                        ->integerNode('height')->isRequired()->end()
                                        ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('web_path')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('not_found_web_path')->isRequired()->cannotBeEmpty()->end()

                                        ->arrayNode('watermark_path')
                                            ->canBeUnset()
                                                ->children()
                                                    ->integerNode('width')->isRequired()->end()
                                                    ->integerNode('height')->isRequired()->end()
                                                    ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
