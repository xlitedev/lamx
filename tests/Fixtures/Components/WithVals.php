<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Components\HtmxComponent;

class WithVals extends HtmxComponent
{
    public function __construct(public string $vals, public int $n = 1) {}

    public function render(): string
    {
        return '<div hx-vals=\''.$this->vals.'\' id="x">{{ $this->n }}</div>';
    }
}
