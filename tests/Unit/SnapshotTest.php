<?php

namespace Xlited\Lamx\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Xlited\Lamx\Exceptions\InvalidSnapshotException;
use Xlited\Lamx\Exceptions\UnserializableValueException;
use Xlited\Lamx\Support\Snapshot;
use Xlited\Lamx\Tests\TestCase;

enum Status: string
{
    case Open = 'open';
}

class SnapshotTest extends TestCase
{
    public function test_values_round_trip(): void
    {
        $date = new CarbonImmutable('2024-03-14 10:00:00.5', 'Europe/Chisinau');

        $props = [
            'int' => 1, 'float' => 1.5, 'bool' => false, 'null' => null, 'string' => 'a"b',
            'array' => ['x' => [1, 2], 'y' => Status::Open],
            'enum' => Status::Open,
            'date' => $date,
            'collection' => collect(['k' => 'v']),
        ];

        $decoded = Snapshot::decode(Snapshot::encode('Foo', array_map(Snapshot::dehydrate(...), $props)));
        $hydrated = array_map(Snapshot::hydrate(...), $decoded['props']);

        $this->assertSame('Foo', $decoded['class']);
        $this->assertSame(1, $hydrated['int']);
        $this->assertSame(1.5, $hydrated['float']);
        $this->assertFalse($hydrated['bool']);
        $this->assertNull($hydrated['null']);
        $this->assertSame('a"b', $hydrated['string']);
        $this->assertSame([1, 2], $hydrated['array']['x']);
        $this->assertSame(Status::Open, $hydrated['array']['y']);
        $this->assertSame(Status::Open, $hydrated['enum']);
        $this->assertInstanceOf(CarbonImmutable::class, $hydrated['date']);
        $this->assertTrue($date->eq($hydrated['date']));
        $this->assertSame('Europe/Chisinau', $hydrated['date']->getTimezone()->getName());
        $this->assertInstanceOf(Collection::class, $hydrated['collection']);
        $this->assertSame(['k' => 'v'], $hydrated['collection']->all());
    }

    public function test_unsupported_values_are_reported(): void
    {
        $this->expectException(UnserializableValueException::class);

        Snapshot::dehydrate(new \stdClass);
    }

    public function test_tampering_is_detected(): void
    {
        $snapshot = Snapshot::encode('Foo', ['a' => 1]);
        [$data, $signature] = explode('.', $snapshot);

        $this->assertSame(['class' => 'Foo', 'props' => ['a' => 1]], Snapshot::decode($snapshot));

        $this->expectException(InvalidSnapshotException::class);
        Snapshot::decode(substr($data, 0, -1).'A.'.$signature);
    }

    public function test_unknown_classes_are_rejected_on_hydration(): void
    {
        $this->expectException(InvalidSnapshotException::class);

        Snapshot::hydrate(['__lamx' => 'model', 'class' => 'Not\\A\\Model', 'key' => 1]);
    }
}
