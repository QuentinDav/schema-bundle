<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration schema for QdSchemaBundle.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('qd_schema');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('nl_to_sql')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable Natural Language to SQL feature')
                        ->end()
                        ->enumNode('strategy')
                            ->values(['local', 'ai', 'hybrid'])
                            ->defaultValue('local')
                            ->info('Generation strategy: local (rule-based), ai (LLM), or hybrid (local + AI fallback)')
                        ->end()
                        ->floatNode('confidence_threshold')
                            ->defaultValue(0.7)
                            ->min(0.0)
                            ->max(1.0)
                            ->info('Minimum confidence threshold for hybrid strategy (0.0 - 1.0)')
                        ->end()
                        ->arrayNode('ai')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('provider')
                                    ->values(['openai', 'anthropic', 'mistral', 'grok', 'ollama'])
                                    ->defaultNull()
                                    ->info('AI provider: openai, anthropic, mistral, grok, or ollama')
                                ->end()
                                ->scalarNode('model')
                                    ->defaultValue('gpt-4-turbo')
                                    ->info('AI model to use (e.g., gpt-4-turbo, claude-3-sonnet, mistral-large)')
                                ->end()
                                ->integerNode('max_tokens')
                                    ->defaultValue(1000)
                                    ->min(100)
                                    ->max(4000)
                                    ->info('Maximum tokens for AI response')
                                ->end()
                                ->floatNode('temperature')
                                    ->defaultValue(0.2)
                                    ->min(0.0)
                                    ->max(2.0)
                                    ->info('Temperature for AI generation (0.0 = deterministic, 2.0 = creative)')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('cost')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->floatNode('warn_threshold')
                                    ->defaultValue(0.10)
                                    ->min(0.0)
                                    ->info('Warn if estimated cost exceeds this amount (USD)')
                                ->end()
                                ->floatNode('max_per_request')
                                    ->defaultValue(0.50)
                                    ->min(0.0)
                                    ->info('Block request if estimated cost exceeds this amount (USD)')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
