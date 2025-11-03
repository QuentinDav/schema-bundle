<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class QdSchemaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // Store NL to SQL configuration as parameters
        if (isset($config['nl_to_sql'])) {
            $container->setParameter('qd_schema.nl_to_sql.enabled', $config['nl_to_sql']['enabled']);
            $container->setParameter('qd_schema.nl_to_sql.strategy', $config['nl_to_sql']['strategy']);
            $container->setParameter('qd_schema.nl_to_sql.confidence_threshold', $config['nl_to_sql']['confidence_threshold']);

            // AI configuration
            if (isset($config['nl_to_sql']['ai'])) {
                $container->setParameter('qd_schema.nl_to_sql.ai.provider', $config['nl_to_sql']['ai']['provider']);
                $container->setParameter('qd_schema.nl_to_sql.ai.model', $config['nl_to_sql']['ai']['model']);
                $container->setParameter('qd_schema.nl_to_sql.ai.max_tokens', $config['nl_to_sql']['ai']['max_tokens']);
                $container->setParameter('qd_schema.nl_to_sql.ai.temperature', $config['nl_to_sql']['ai']['temperature']);
            }

            // Cost configuration
            if (isset($config['nl_to_sql']['cost'])) {
                $container->setParameter('qd_schema.nl_to_sql.cost.warn_threshold', $config['nl_to_sql']['cost']['warn_threshold']);
                $container->setParameter('qd_schema.nl_to_sql.cost.max_per_request', $config['nl_to_sql']['cost']['max_per_request']);
            }
        }
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
