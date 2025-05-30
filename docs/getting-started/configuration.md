# Configuration

AutoMapper ships with a default configuration that should work out of the box. However, you can customize it to your needs.

You need to create a `Configuration` object and pass it to the `AutoMapper::create()` method.

```php
use AutoMapper\Configuration;
use AutoMapper\AutoMapper;

$configuration = new Configuration(
    classPrefix: 'Mapper_'
    constructorStrategy: 'auto',
    dateTimeFormat: \DateTimeInterface::RFC3339,
    attributesChecking: true,
    autoRegister: true,
    mapPrivateProperties: true,
    allowReadonlyTargetToPopulate: false,
);
$autoMapper = AutoMapper::create(configuration: $configuration);
```

The `Configuration` object allows you to define the following options:

* `classPrefix` (default: `AutoMapper_`)

The prefix to use for the generated mappers class names. It can be useful to change it you have a different AutoMapper 
instance in your application and they should not conflict when mapping the same classes.

* `constructorStrategy` (default: `auto`)

If the generated mapper should use the constructor to instantiate the target. When set to auto, AutoMapper will use the
constructor if all mandatory properties are present in the source object, otherwise it will not use it.
Set it to `never` to never use the constructor.
Set it to `always` to always use the constructor, even if some mandatory properties are missing. In this cas you may need
to [provide a default value for the missing properties using the context](context.md).

* `dateTimeFormat` (default: `\DateTimeInterface::RFC3339`)

The format to use to transform a date from/to a string. It may be useful if all your dates are in a specific format and 
you want to avoid repeating the format in all your mappings.

* `attributesChecking` (default: `true`)

Setting this to false will not generate the code to check for `allowed_attributes` and `ignored_attributes` at runtime. 
Some applications may not need this feature and disabling it will improve the performance as it avoid a check for each 
property at runtime.

* `autoRegister` (default: `true`)

AutoMapper generate the mappers on the fly when they are needed, also it store them in a cache directory. On the next
run it will try to fetch the mapper from the cache directory.

Caching on the fly may slow down the first request or the disk may not be writable resulting in an error in some environments.

Setting this to false will make the `AutoMapper` throw an exception if the mapper is not found in the cache directory.

This can be useful if you want to pre generate all the mappers and have tests to ensure that all the mappers are
generated.

* `allowExtraProperties` (default: `false`)

Settings this to true will allow the mapper to map extra properties from the source object to the target object when
the source or the target implements the `ArrayAccess` interface.
