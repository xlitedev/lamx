<?php

namespace Xlited\Lamx\Features;

use Illuminate\Container\Container;
use ReflectionProperty;
use Xlited\Lamx\Exceptions\InvalidSnapshotException;
use Xlited\Lamx\Exceptions\UnserializableValueException;
use Xlited\Lamx\Support\Snapshot;

/**
 * The public properties of a component are its state. When the component is
 * rendered, an encrypted snapshot of them is attached to the root element as
 * hx-vals:inherited, so every htmx request made from inside the component
 * sends it back and the very same component can be re-created on the
 * server before the action runs.
 *
 * Supported values: scalars, arrays, enums, dates, collections and Eloquent
 * models (stored by key and re-fetched). Anything else is left out and
 * re-created by the constructor; when the constructor does not know the
 * property, a warning is logged, because its value will be lost.
 */
trait HandlesState
{
    /**
     * Set to true to render the component without a state snapshot.
     */
    protected bool $stateless = false;

    /**
     * Re-create a component from a snapshot produced by snapshot().
     *
     * @throws InvalidSnapshotException
     */
    public static function fromSnapshot(string $snapshot): static
    {
        $payload = Snapshot::decode($snapshot);

        if ($payload['class'] !== static::class) {
            throw new InvalidSnapshotException('The component state does not belong to ['.static::class.'].');
        }

        return static::hydrate($payload['props']);
    }

    /**
     * Create a component from dehydrated property values: constructor
     * parameters are passed to the constructor, the rest are assigned.
     */
    public static function hydrate(array $props): static
    {
        $props = array_map(Snapshot::hydrate(...), $props);

        $component = static::make($props);

        $constructorParameters = static::extractConstructorParameters();

        foreach ($props as $name => $value) {
            if (in_array($name, $constructorParameters, true) || ! property_exists($component, $name)) {
                continue;
            }

            $property = new ReflectionProperty($component, $name);

            if ($property->isPublic() && ! $property->isStatic()) {
                $component->{$name} = $value;
            }
        }

        return $component;
    }

    /**
     * The encrypted snapshot of the component state.
     */
    public function snapshot(): string
    {
        return Snapshot::encode(static::class, $this->stateProperties());
    }

    /**
     * Assign the given values to public properties.
     */
    public function fill(array $values): static
    {
        foreach ($values as $name => $value) {
            if (property_exists($this, $name) && (new ReflectionProperty($this, $name))->isPublic()) {
                $this->{$name} = $value;
            }
        }

        return $this;
    }

    protected function snapshotIfStateful(): ?string
    {
        if ($this->stateless) {
            return null;
        }

        $props = $this->stateProperties();

        return $props === [] ? null : Snapshot::encode(static::class, $props);
    }

    /**
     * The dehydrated public properties that make up the state.
     */
    protected function stateProperties(): array
    {
        $props = [];
        $constructorParameters = static::extractConstructorParameters();

        foreach ($this->extractPublicProperties() as $name => $value) {
            if (in_array($name, ['attributes', 'componentName'], true)) {
                continue;
            }

            try {
                $props[$name] = Snapshot::dehydrate($value);
            } catch (UnserializableValueException $e) {
                // Constructor parameters are re-created; anything else is lost.
                if (! in_array($name, $constructorParameters, true)) {
                    $this->warnAboutDroppedProperty($name, $e);
                }
            }
        }

        return $props;
    }

    protected function warnAboutDroppedProperty(string $name, UnserializableValueException $e): void
    {
        $app = Container::getInstance();

        if ($app->bound('log')) {
            $app->make('log')->warning(sprintf(
                'Lamx left [%s::$%s] out of the component state, so it will not survive the next request: %s',
                static::class, $name, $e->getMessage()
            ));
        }
    }
}
