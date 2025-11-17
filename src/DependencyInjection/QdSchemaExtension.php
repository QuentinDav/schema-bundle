<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\DependencyInjection;

use Qd\SchemaBundle\Service\NlToSql\Generator\AnthropicGenerator;
use Qd\SchemaBundle\Service\NlToSql\Generator\GeminiGenerator;
use Qd\SchemaBundle\Service\NlToSql\Generator\GrokAiGenerator;
use Qd\SchemaBundle\Service\NlToSql\Generator\MistralGenerator;
use Qd\SchemaBundle\Service\NlToSql\Generator\OpenAiGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

final class QdSchemaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if (isset($config['nl_to_sql'])) {
            $container->setParameter('qd_schema.nl_to_sql.enabled', $config['nl_to_sql']['enabled']);

            if (isset($config['nl_to_sql']['ai'])) {
                $aiConfig = $config['nl_to_sql']['ai'];

                $container->setParameter('qd_schema.nl_to_sql.ai.provider', $aiConfig['provider']);
                $container->setParameter('qd_schema.nl_to_sql.ai.api_key', $aiConfig['api_key']);
                $container->setParameter('qd_schema.nl_to_sql.ai.max_tokens', $aiConfig['max_tokens']);
                $container->setParameter('qd_schema.nl_to_sql.ai.temperature', $aiConfig['temperature']);

                $model = $aiConfig['model'] ?? $this->getDefaultModel($aiConfig['provider']);
                $container->setParameter('qd_schema.nl_to_sql.ai.model', $model);

                if ($aiConfig['provider'] && $aiConfig['api_key']) {
                    $this->registerAiGenerator($container, $aiConfig['provider'], $aiConfig['api_key'], $model, $aiConfig['max_tokens'], $aiConfig['temperature']);
                }
            }

            if (isset($config['nl_to_sql']['cost'])) {
                $container->setParameter('qd_schema.nl_to_sql.cost.warn_threshold', $config['nl_to_sql']['cost']['warn_threshold']);
                $container->setParameter('qd_schema.nl_to_sql.cost.max_per_request', $config['nl_to_sql']['cost']['max_per_request']);
            }
        }
    }

    private function getDefaultModel(?string $provider): string
    {
        return match($provider) {
            'openai' => 'gpt-4-turbo',
            'anthropic' => 'claude-3-5-sonnet-20241022',
            'mistral' => 'mistral-large-latest',
            'grok' => 'grok-beta',
            'gemini' => 'gemini-2.0-flash',
            default => 'gpt-4-turbo',
        };
    }

    private function registerAiGenerator(
        ContainerBuilder $container,
        string $provider,
        string $apiKey,
        string $model,
        int $maxTokens,
        float $temperature
    ): void {
        $generatorClass = match($provider) {
            'openai' => OpenAiGenerator::class,
            'anthropic' => AnthropicGenerator::class,
            'mistral' => MistralGenerator::class,
            'grok' => GrokAiGenerator::class,
            'gemini' => GeminiGenerator::class,
            default => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };

        $definition = new Definition($generatorClass);
        $definition->setArguments([
            new Reference('http_client'),
            new Reference('Qd\SchemaBundle\Service\NlToSql\PromptBuilder'),
            new Reference('Qd\SchemaBundle\Service\NlToSql\CostEstimator'),
            $apiKey,
            $this->getBaseUrl($provider),
            $model,
            $maxTokens,
            $temperature,
            new Reference('logger'),
        ]);
        $definition->setPublic(false);

        $container->setDefinition('qd_schema.ai_generator', $definition);
    }

    private function getBaseUrl(string $provider): string
    {
        return match($provider) {
            'openai' => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            'mistral' => 'https://api.mistral.ai/v1',
            'grok' => 'https://api.x.ai/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            default => '',
        };
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'QdSchemaBundle' => [
                        'is_bundle' => true,
                        'type'      => 'attribute',
                        'dir'       => 'Entity',
                        'prefix'    => 'Qd\\SchemaBundle\\Entity',
                        'alias'     => 'QdSchema',
                    ],
                ],
            ],
        ]);
    }
}
