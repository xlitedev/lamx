<?php

namespace Xlited\Lamx\Tests\Fixtures\Components;

use Illuminate\Database\Eloquent\Collection;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Tests\Fixtures\Models\Post;

class PostEditor extends HtmxComponent
{
    public function __construct(public Post $post, public Collection $related) {}

    public function rename(string $title): void
    {
        $this->post->update(['title' => $title]);
    }
}
