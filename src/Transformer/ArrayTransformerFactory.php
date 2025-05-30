<?php

declare(strict_types=1);

namespace AutoMapper\Transformer;

use AutoMapper\Metadata\MapperMetadata;
use AutoMapper\Metadata\SourcePropertyMetadata;
use AutoMapper\Metadata\TargetPropertyMetadata;
use AutoMapper\Metadata\TypesMatching;
use Symfony\Component\PropertyInfo\Type;

/**
 * Create a decorated transformer to handle array type.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 *
 * @internal
 */
final class ArrayTransformerFactory extends AbstractUniqueTypeTransformerFactory implements PrioritizedTransformerFactoryInterface, ChainTransformerFactoryAwareInterface
{
    use ChainTransformerFactoryAwareTrait;

    protected function createTransformer(Type $sourceType, Type $targetType, SourcePropertyMetadata $source, TargetPropertyMetadata $target, MapperMetadata $mapperMetadata): ?TransformerInterface
    {
        if (!($sourceType->isCollection() || ($sourceType->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT && $sourceType->getClassName() === \Generator::class))) {
            return null;
        }

        if (!$targetType->isCollection()) {
            return null;
        }

        $sourceCollections = $sourceType->getCollectionValueTypes();
        $targetCollections = $targetType->getCollectionValueTypes();

        if ([] === $sourceCollections && [] !== $targetCollections) {
            // consider array as a collection of array
            $sourceCollections = [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, false)];
        }

        if ([] !== $sourceCollections && [] === $targetCollections) {
            // consider array as a collection of array
            $targetCollections = [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, false)];
        }

        if ([] === $sourceCollections || [] === $targetCollections) {
            return new DictionaryTransformer(new CopyTransformer());
        }

        $types = TypesMatching::fromSourceAndTargetTypes($sourceCollections, $targetCollections);
        $subItemTransformer = $this->chainTransformerFactory->getTransformer($types, $source, $target, $mapperMetadata);

        if (null !== $subItemTransformer) {
            if ($subItemTransformer instanceof ObjectTransformer) {
                $subItemTransformer->deepTargetToPopulate = false;
            }

            $sourceCollectionKeyTypes = $sourceType->getCollectionKeyTypes();
            $sourceCollectionKeyType = $sourceCollectionKeyTypes[0] ?? null;

            if ($sourceCollectionKeyType instanceof Type && Type::BUILTIN_TYPE_INT !== $sourceCollectionKeyType->getBuiltinType()) {
                return new DictionaryTransformer($subItemTransformer);
            }

            return new ArrayTransformer($subItemTransformer);
        }

        return null;
    }

    public function getPriority(): int
    {
        return 4;
    }
}
