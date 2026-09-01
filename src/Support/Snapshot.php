<?php

namespace Xlited\Lamx\Support;

use BackedEnum;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use RuntimeException;
use Xlited\Lamx\Exceptions\InvalidSnapshotException;
use Xlited\Lamx\Exceptions\UnserializableValueException;

/**
 * A snapshot is the signed, client-carried state of a component: its public
 * properties, plus the class they belong to. It travels in the "hx-vals" of
 * the component root, so every request made from inside the component
 * brings the state back and the component can be re-hydrated on the server.
 */
class Snapshot
{
    /**
     * Encode and sign the given component state.
     */
    public static function encode(string $class, array $props): string
    {
        $payload = json_encode(['class' => $class, 'props' => $props], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $data = static::base64UrlEncode($payload);

        return $data.'.'.static::sign($data);
    }

    /**
     * Verify and decode a snapshot into ['class' => string, 'props' => array].
     *
     * @throws InvalidSnapshotException
     */
    public static function decode(string $snapshot): array
    {
        [$data, $signature] = array_pad(explode('.', $snapshot, 2), 2, '');

        if ($data === '' || ! hash_equals(static::sign($data), $signature)) {
            throw new InvalidSnapshotException('The component state signature is invalid.');
        }

        $payload = json_decode(static::base64UrlDecode($data), true);

        if (! is_array($payload) || ! is_string($payload['class'] ?? null) || ! is_array($payload['props'] ?? null)) {
            throw new InvalidSnapshotException('The component state is malformed.');
        }

        return $payload;
    }

    /**
     * Convert a property value into something JSON friendly that can be hydrated later.
     *
     * @throws UnserializableValueException
     */
    public static function dehydrate(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            is_array($value) => array_map(static::dehydrate(...), $value),
            $value instanceof BackedEnum => ['__lamx' => 'enum', 'class' => $value::class, 'value' => $value->value],
            $value instanceof Model => static::dehydrateModel($value),
            $value instanceof EloquentCollection => static::dehydrateModels($value),
            $value instanceof DateTimeInterface => [
                '__lamx' => 'datetime',
                'class' => get_class($value),
                'value' => $value->format('Y-m-d H:i:s.u'),
                'timezone' => $value->getTimezone()->getName(),
            ],
            $value instanceof Collection => [
                '__lamx' => 'collection',
                'class' => get_class($value),
                'items' => static::dehydrate($value->all()),
            ],
            default => throw new UnserializableValueException(
                'Values of type ['.get_debug_type($value).'] cannot be stored in a component snapshot.'
            ),
        };
    }

    /**
     * Restore a value produced by dehydrate().
     */
    public static function hydrate(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! isset($value['__lamx'])) {
            return array_map(static::hydrate(...), $value);
        }

        return match ($value['__lamx']) {
            'enum' => static::hydrateEnum($value),
            'model' => static::hydrateModel($value),
            'models' => static::hydrateModels($value),
            'datetime' => static::hydrateDateTime($value),
            'collection' => static::hydrateCollection($value),
            default => throw new InvalidSnapshotException('Unknown value type in component state.'),
        };
    }

    protected static function dehydrateModel(Model $model): array
    {
        if (! $model->exists) {
            throw new UnserializableValueException('Unsaved models cannot be stored in a component snapshot.');
        }

        return ['__lamx' => 'model', 'class' => get_class($model), 'key' => $model->getKey()];
    }

    protected static function dehydrateModels(EloquentCollection $models): array
    {
        if ($models->isEmpty()) {
            return ['__lamx' => 'collection', 'class' => get_class($models), 'items' => []];
        }

        try {
            $class = $models->getQueueableClass();
        } catch (LogicException $e) {
            throw new UnserializableValueException($e->getMessage(), 0, $e);
        }

        return ['__lamx' => 'models', 'class' => $class, 'keys' => array_values($models->getQueueableIds())];
    }

    protected static function hydrateEnum(array $value): BackedEnum
    {
        $class = static::expectClass($value, BackedEnum::class);

        return $class::from($value['value'] ?? null);
    }

    protected static function hydrateModel(array $value): Model
    {
        $class = static::expectClass($value, Model::class);

        return $class::query()->findOrFail($value['key'] ?? null);
    }

    protected static function hydrateModels(array $value): EloquentCollection
    {
        $class = static::expectClass($value, Model::class);
        $keys = array_values((array) ($value['keys'] ?? []));

        $models = $class::query()->findMany($keys)->keyBy(fn (Model $model) => $model->getKey());

        // findMany() does not preserve order, so restore the original one.
        return $models->only($keys)->values()->sortBy(fn (Model $model) => array_search($model->getKey(), $keys, false))->values();
    }

    protected static function hydrateDateTime(array $value): DateTimeInterface
    {
        $class = static::expectClass($value, DateTimeInterface::class);

        return new $class($value['value'] ?? 'now', new DateTimeZone($value['timezone'] ?? date_default_timezone_get()));
    }

    protected static function hydrateCollection(array $value): Collection
    {
        $class = static::expectClass($value, Collection::class);

        return new $class(static::hydrate((array) ($value['items'] ?? [])));
    }

    protected static function expectClass(array $value, string $type): string
    {
        $class = $value['class'] ?? null;

        if (! is_string($class) || ! class_exists($class) || ! is_a($class, $type, true)) {
            throw new InvalidSnapshotException('Invalid class in component state.');
        }

        return $class;
    }

    protected static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, static::key());
    }

    protected static function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if ($key === '') {
            throw new RuntimeException('Lamx needs an application key (APP_KEY) to sign component state.');
        }

        return $key;
    }

    protected static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
