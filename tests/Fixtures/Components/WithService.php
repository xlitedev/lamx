<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use stdClass;
use Xlited\Lamx\Components\HtmxComponent;

class WithService extends HtmxComponent
{
    /** Not a constructor parameter, so it cannot be re-created: dropping it deserves a warning. */
    public stdClass $meta;

    public function __construct(public stdClass $config = new stdClass)
    {
        $this->meta = new stdClass;
    }

    public function render(): string
    {
        return '<div>ok</div>';
    }
}
