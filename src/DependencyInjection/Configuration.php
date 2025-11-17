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
                        ->arrayNode('ai')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('provider')
                                    ->values(['openai', 'anthropic', 'mistral', 'grok', 'gemini'])
                                    ->defaultNull()
                                    ->info('AI provider: openai, anthropic, mistral, grok, or gemini')
                                ->end()
                                ->scalarNode('api_key')
                                    ->defaultNull()
                                    ->info('API key for the AI provider (use %env(PROVIDER_API_KEY)%)')
                                ->end()
                                ->scalarNode('model')
                                    ->defaultNull()
                                    ->info('AI model to use. Defaults: gpt-4-turbo (OpenAI), claude-3-5-sonnet-20241022 (Anthropic), mistral-large-latest (Mistral), grok-beta (Grok), gemini-2.0-flash (Gemini)')
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
