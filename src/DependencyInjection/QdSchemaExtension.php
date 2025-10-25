<?php
namespace Qd\SchemaBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

final class QdSchemaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config')))
            ->load('services.yaml');
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
