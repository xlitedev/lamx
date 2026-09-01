<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Attributes\Bindable;
use Xlited\Lamx\Components\HtmxComponent;

class Search extends HtmxComponent
{
    #[Bindable]
    public string $query = '';

    #[Bindable]
    public ?int $page = null;

    #[Bindable]
    public bool $exact = false;

    #[Bindable]
    public float $ratio = 1.0;

    #[Bindable]
    public array $tags = [];

    #[Bindable(as: 'sortBy')]
    public string $sort = 'name';

    public string $secret = 'server';

    public array $log = [];

    public function __construct(public string $rootData = '') {}

    protected function updatedQuery(string $value, string $old): void
    {
        $this->page = null;
        $this->log[] = "query:{$old}>{$value}";
    }

    // Public on purpose: a hook is never an action.
    public function updatedPage(?int $value, ?int $old): void
    {
        $this->log[] = 'page:'.json_encode($old).'>'.json_encode($value);
    }

    public function search(): void
    {
        $this->log[] = 'searched';
    }

    public function render(): string
    {
        $data = $this->rootData !== '' ? " x-data='{$this->rootData}'" : '';

        return '<div id="search"'.$data.'>{{ $this->query }}|{{ json_encode($this->page) }}|{{ json_encode($this->exact) }}'
            .'|{{ $this->ratio }}|{{ implode(",", $this->tags) }}|{{ $this->sort }}|{{ $this->secret }}|{{ implode(";", $this->log) }}</div>';
    }
}
