<?php

declare(strict_types=1);

namespace AutoMapper\Extractor;

use AutoMapper\Exception\CompileException;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use Symfony\Component\PropertyInfo\Type;

/**
 * Writes mutator tell how to write to a property.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 *
 * @internal
 */
final class WriteMutator
{
    use GetTypeTrait;

    public const TYPE_METHOD = 1;
    public const TYPE_PROPERTY = 2;
    public const TYPE_ARRAY_DIMENSION = 3;
    public const TYPE_CONSTRUCTOR = 4;
    public const TYPE_ADDER_AND_REMOVER = 5;

    public function __construct(
        public readonly int $type,
        public readonly string $property,
        public readonly bool $private = false,
        public readonly ?\ReflectionParameter $parameter = null,
        private readonly ?string $removeMethodName = null,
    ) {
    }

    /**
     * Get AST expression for writing from a value to an output.
     *
     * @throws CompileException
     */
    public function getExpression(Expr $output, Expr $value, bool $byRef = false): Expr
    {
        if (self::TYPE_METHOD === $this->type || self::TYPE_ADDER_AND_REMOVER === $this->type) {
            /*
             * Create method call expression to write value
             *
             * $output->method($value);
             */
            return new Expr\MethodCall($output, $this->property, [
                new Arg($value),
            ]);
        }

        if (self::TYPE_PROPERTY === $this->type) {
            if ($this->private) {
                /*
                 * Use hydrate callback to write value
                 *
                 * $this->hydrateCallbacks['propertyName']($output, $value);
                 */
                return new Expr\FuncCall(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'hydrateCallbacks'), new Scalar\String_($this->property)),
                    [
                        new Arg($output),
                        new Arg($value),
                    ]
                );
            }

            /*
             * Create property expression to write value
             *
             * $output->propertyName &= $value;
             */
            if ($byRef) {
                return new Expr\AssignRef(new Expr\PropertyFetch($output, $this->property), $value);
            }

            return new Expr\Assign(new Expr\PropertyFetch($output, $this->property), $value);
        }

        if (self::TYPE_ARRAY_DIMENSION === $this->type) {
            /*
             * Create array write expression to write value
             *
             * $output['propertyName'] &= $value;
             */
            if ($byRef) {
                return new Expr\AssignRef(new Expr\ArrayDimFetch($output, new Scalar\String_($this->property)), $value);
            }

            return new Expr\Assign(new Expr\ArrayDimFetch($output, new Scalar\String_($this->property)), $value);
        }

        throw new CompileException('Invalid accessor for write expression');
    }

    public function getRemoveExpression(Expr $object, Expr $value): ?Expr
    {
        if (self::TYPE_ADDER_AND_REMOVER === $this->type && $this->removeMethodName) {
            /*
             * Create method call expression to remove value
             *
             * $object->removeMethodName($value);
             */
            return new Expr\MethodCall($object, $this->removeMethodName, [
                new Arg($value),
            ]);
        }

        return null;
    }

    /**
     * Get AST expression for binding closure when dealing with private property.
     */
    public function getHydrateCallback(string $className): ?Expr
    {
        if (self::TYPE_PROPERTY !== $this->type || !$this->private) {
            return null;
        }

        /*
         * Create hydrate callback for this mutator
         *
         * \Closure::bind(function ($object, $value) {
         *    $object->propertyName = $value;
         * }, null, $className)
         */
        return new Expr\StaticCall(new Name\FullyQualified(\Closure::class), 'bind', [
            new Arg(new Expr\Closure([
                'params' => [
                    new Param(new Expr\Variable('object')),
                    new Param(new Expr\Variable('value')),
                ],
                'stmts' => [
                    new Stmt\Expression(new Expr\Assign(new Expr\PropertyFetch(new Expr\Variable('object'), $this->property), new Expr\Variable('value'))),
                ],
            ])),
            new Arg(new Expr\ConstFetch(new Name('null'))),
            new Arg(new Scalar\String_($className)),
        ]);
    }

    /**
     * @return Type[]|null
     */
    public function getTypes(string $target): ?array
    {
        if (self::TYPE_METHOD === $this->type && (class_exists($target) || interface_exists($target))) {
            try {
                $reflectionMethod = new \ReflectionMethod($target, $this->property);

                if ($types = $this->extractFromDocBlock(
                    $reflectionMethod->getDocComment(),
                    $target,
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getParameters()[0]->name,
                    '@param'
                )) {
                    return $types;
                }

                $parameters = $reflectionMethod->getParameters();

                if (empty($parameters)) {
                    return null;
                }

                $firstParameter = $parameters[0];
                $reflectionType = $firstParameter->getType();

                if (null === $reflectionType) {
                    return null;
                }

                return $this->extractFromReflectionType($reflectionType, $reflectionMethod->getDeclaringClass());
            } catch (\ReflectionException $e) {
                return null;
            }
        }

        if (self::TYPE_PROPERTY === $this->type && (class_exists($target) || interface_exists($target))) {
            try {
                $reflectionProperty = new \ReflectionProperty($target, $this->property);

                if ($reflectionProperty->isPromoted()) {
                    if ($types = $this->extractFromDocBlock(
                        $reflectionProperty->getDeclaringClass()->getConstructor()?->getDocComment(),
                        $target,
                        $reflectionProperty->getDeclaringClass()->getName(),
                        $this->property,
                        '@param'
                    )) {
                        return $types;
                    }
                }

                if ($types = $this->extractFromDocBlock(
                    $reflectionProperty->getDocComment(),
                    $target,
                    $reflectionProperty->getDeclaringClass()->getName(),
                    $this->property,
                    '@var'
                )) {
                    return $types;
                }

                $reflectionType = $reflectionProperty->getType();

                if ($reflectionType === null) {
                    return null;
                }

                return $this->extractFromReflectionType($reflectionType, $reflectionProperty->getDeclaringClass());
            } catch (\ReflectionException $e) {
                return null;
            }
        }

        return null;
    }
}
