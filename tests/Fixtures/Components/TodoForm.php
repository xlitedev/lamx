<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Xlited\Lamx\Components\HtmxComponent;

class TodoForm extends HtmxComponent
{
    public string $title = '';

    protected function rules(): array
    {
        return ['title' => 'required|min:3'];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->title = 'saved: '.$data['title'];
    }
}
