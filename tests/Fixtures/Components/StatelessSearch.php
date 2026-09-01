<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Attributes\Bindable;
use Xlited\Lamx\Components\HtmxComponent;

class StatelessSearch extends HtmxComponent
{
    protected bool $stateless = true;

    #[Bindable]
    public string $query = '';

    public function render(): string
    {
        return '<div></div>';
    }
}
