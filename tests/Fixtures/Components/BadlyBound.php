<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Illuminate\Support\Collection;
use Xlited\Lamx\Attributes\Bindable;
use Xlited\Lamx\Components\HtmxComponent;

class BadlyBound extends HtmxComponent
{
    #[Bindable]
    public Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
