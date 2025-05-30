<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AutoMapper\Event\PropertyMetadataEvent;
use AutoMapper\EventListener\Symfony\AdvancedNameConverterListener;
use AutoMapper\EventListener\Symfony\SerializerGroupListener;
use AutoMapper\EventListener\Symfony\SerializerIgnoreListener;
use AutoMapper\EventListener\Symfony\SerializerMaxDepthListener;
use AutoMapper\Generator\Shared\ClassDiscriminatorResolver;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(SerializerMaxDepthListener::class)
            ->args([service('serializer.mapping.class_metadata_factory')])
            ->tag('kernel.event_listener', ['event' => PropertyMetadataEvent::class, 'priority' => -64])

        ->set(SerializerGroupListener::class)
            ->args([service('serializer.mapping.class_metadata_factory')])
            ->tag('kernel.event_listener', ['event' => PropertyMetadataEvent::class, 'priority' => -64])

        ->set(SerializerIgnoreListener::class)
            ->args([service('serializer.mapping.class_metadata_factory')])
            ->tag('kernel.event_listener', ['event' => PropertyMetadataEvent::class, 'priority' => -64])

        ->set(ClassDiscriminatorResolver::class)
            ->args([service('automapper.mapping.class_discriminator_from_class_metadata')])

        ->set('automapper.mapping.class_discriminator_from_class_metadata', ClassDiscriminatorFromClassMetadata::class)
            ->args([service('serializer.mapping.class_metadata_factory')])

        ->set('automapper.mapping.metadata_aware_name_converter', MetadataAwareNameConverter::class)
            ->args([service('serializer.mapping.class_metadata_factory')])

        ->set(AdvancedNameConverterListener::class)
            ->args([service('automapper.mapping.metadata_aware_name_converter')])
            ->tag('kernel.event_listener', ['event' => PropertyMetadataEvent::class, 'priority' => -64])
    ;
};
