<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Illuminate\Http\Request;
use Xlited\Lamx\Components\HtmxComponent;

class Counter extends HtmxComponent
{
    public int $count;

    public array $history = [];

    public function __construct(int $count = 0, public string $label = 'Count')
    {
        $this->count = $count;
    }

    public function increment(): void
    {
        $this->history[] = $this->count;
        $this->count++;
    }

    public function add(int $amount, Request $request): void
    {
        $this->count += $amount;
        $this->label = $request->input('label', $this->label);
    }

    public function reset(): static
    {
        return static::make(['label' => 'Fresh']);
    }

    public function twice(): array
    {
        return [static::make(['count' => 1]), static::make(['count' => 2])];
    }

    public function home()
    {
        return redirect('/home');
    }

    public function text(): string
    {
        return 'plain '.$this->count;
    }

    public function secret(): string
    {
        return 'never';
    }

    protected function helper(): string
    {
        return 'hidden';
    }
}
