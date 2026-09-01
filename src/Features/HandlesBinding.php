<?php

namespace Xlited\Lamx\Features;

use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Xlited\Lamx\Attributes\Bindable;
use Xlited\Lamx\Exceptions\InvalidBindingException;

/**
 * Public properties marked #[Bindable] are shared with Alpine.js. They are
 * rendered into the root element's x-data, so the view can use x-model,
 * x-text, x-show... and the page reacts without a round trip. Every htmx
 * request made from inside the component sends the current values back
 * (@lamxScripts puts them in the "_lamx_data" field), where they are
 * coerced to the property type and applied before the action runs.
 *
 * The state snapshot stays the trusted baseline; the bound values are
 * treated like any other request input: whitelisted and typed. Unknown keys
 * (local Alpine state) are ignored, uncoercible values are a 400.
 */
trait HandlesBinding
{
    /** @var array<class-string, array<string, ReflectionProperty>> */
    protected static array $bindableProperties = [];

    /**
     * The bindable properties, keyed by the name Alpine sees.
     *
     * @return array<string, ReflectionProperty>
     */
    public static function bindableProperties(): array
    {
        if (isset(static::$bindableProperties[static::class])) {
            return static::$bindableProperties[static::class];
        }

        $properties = [];

        foreach ((new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attribute = $property->getAttributes(Bindable::class)[0] ?? null;

            if ($property->isStatic() || $attribute === null) {
                continue;
            }

            $type = $property->getType();

            if ($type !== null && (! $type instanceof ReflectionNamedType || ! in_array($type->getName(), ['int', 'float', 'string', 'bool', 'array'], true))) {
                throw new LogicException(sprintf(
                    'The bindable property [%s::$%s] must be an int, float, string, bool or array (nullable or not).',
                    static::class, $property->getName()
                ));
            }

            $name = $attribute->newInstance()->as ?? $property->getName();

            if (isset($properties[$name])) {
                throw new LogicException(sprintf('Two bindable properties of [%s] share the name [%s].', static::class, $name));
            }

            $properties[$name] = $property;
        }

        return static::$bindableProperties[static::class] = $properties;
    }

    /**
     * The values Alpine starts with, keyed by the name Alpine sees.
     */
    public function bindableData(): array
    {
        $data = [];

        foreach (static::bindableProperties() as $name => $property) {
            if (! $property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);

            if (is_array($value) && ! static::isFlatArray($value)) {
                throw new LogicException(sprintf(
                    'The bindable array [%s::$%s] may only contain scalars.', static::class, $property->getName()
                ));
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /**
     * Decode the JSON object the browser sent in the "_lamx_data" field.
     *
     * @throws InvalidBindingException
     */
    public static function decodeBindings(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidBindingException('The bound component data is malformed.');
        }

        return $data;
    }

    /**
     * Apply the values proposed by the browser to the bindable properties,
     * coerced to their type. Unknown keys are ignored. The updated{Property}
     * hook is called for every value that actually changed.
     *
     * @throws InvalidBindingException
     */
    public function applyBindings(array $data): static
    {
        foreach (static::bindableProperties() as $name => $property) {
            if (! array_key_exists($name, $data)) {
                continue;
            }

            $value = static::coerceBinding($property, $data[$name]);
            $initialized = $property->isInitialized($this);
            $old = $initialized ? $property->getValue($this) : null;

            if ($initialized && $old === $value) {
                continue;
            }

            $property->setValue($this, $value);

            $this->callUpdatedHook($property->getName(), $value, $old);
        }

        return $this;
    }

    /**
     * The "updated{Property}" hook methods of the bindable properties. They
     * may be public or protected, but are never actions.
     *
     * @return array<int, string>
     */
    public static function bindingHooks(): array
    {
        return array_map(
            fn (ReflectionProperty $property) => 'updated'.Str::studly($property->getName()),
            array_values(static::bindableProperties())
        );
    }

    protected function callUpdatedHook(string $property, mixed $value, mixed $old): void
    {
        $method = 'updated'.Str::studly($property);

        if (method_exists($this, $method)) {
            (new ReflectionMethod($this, $method))->invoke($this, $value, $old);
        }
    }

    /**
     * Coerce a value from the browser to the declared type of the property.
     *
     * @throws InvalidBindingException
     */
    protected static function coerceBinding(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        $kind = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $fail = fn (string $reason) => throw InvalidBindingException::forProperty($property->getName(), $reason);

        // A cleared <input> sends "": that is "nothing" for every type but string.
        if ($value === '' && $kind !== null && $kind !== 'string') {
            $value = null;
        }

        if ($value === null) {
            return $type === null || $type->allowsNull() ? null : $fail('must not be empty');
        }

        return match ($kind) {
            'int' => match (true) {
                is_int($value) => $value,
                is_float($value) && floor($value) === $value => (int) $value,
                is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
                default => $fail('must be an integer'),
            },
            'float' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
                ? (float) $value
                : $fail('must be a number'),
            'string' => is_string($value) || is_int($value) || is_float($value)
                ? (string) $value
                : $fail('must be a string'),
            'bool' => match (true) {
                is_bool($value) => $value,
                in_array($value, [1, '1', 'true', 'on'], true) => true,
                in_array($value, [0, '0', 'false', 'off'], true) => false,
                default => $fail('must be a boolean'),
            },
            'array' => is_array($value) && static::isFlatArray($value)
                ? $value
                : $fail('must be a list of scalars'),
            default => is_scalar($value) || (is_array($value) && static::isFlatArray($value))
                ? $value
                : $fail('must be a scalar or a list of scalars'),
        };
    }

    protected static function isFlatArray(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && ! is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
