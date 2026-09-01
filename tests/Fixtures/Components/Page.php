<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Components\HtmxComponent;

class Page extends HtmxComponent
{
    public function __construct(public string $title = 'Home') {}

    protected function layoutParams(): array
    {
        return ['pageTitle' => $this->title];
    }
}
