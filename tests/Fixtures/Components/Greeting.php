<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Components\HtmxComponent;

class Greeting extends HtmxComponent
{
    protected bool $stateless = true;

    public function __construct(public string $name = 'World') {}
}
