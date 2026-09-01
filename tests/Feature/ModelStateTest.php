<?php

namespace Xlited\Lamx\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Support\Snapshot;
use Xlited\Lamx\Tests\Fixtures\Components\PostEditor;
use Xlited\Lamx\Tests\Fixtures\Models\Post;
use Xlited\Lamx\Tests\TestCase;

class ModelStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function test_models_and_collections_travel_by_key_and_are_refetched(): void
    {
        $post = Post::create(['title' => 'First']);
        $b = Post::create(['title' => 'B']);
        $a = Post::create(['title' => 'A']);

        $component = PostEditor::make(['post' => $post, 'related' => Post::whereIn('id', [$a->id, $b->id])->orderBy('title')->get()]);

        $snapshot = Snapshot::decode($component->snapshot());
        $this->assertSame(['__lamx' => 'model', 'class' => Post::class, 'key' => 1], $snapshot['props']['post']);
        $this->assertSame(['__lamx' => 'models', 'class' => Post::class, 'keys' => [3, 2]], $snapshot['props']['related']);

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post('/lamx/lamx-test::post-editor/rename', ['title' => 'Renamed', HtmxComponent::STATE_KEY => $component->snapshot()]);

        $response->assertOk()->assertSee('<h1>Renamed</h1>', false)->assertSee('<li>A</li><li>B</li>', false);
        $this->assertSame('Renamed', $post->fresh()->title);
    }

    public function test_deleted_models_result_in_a_404(): void
    {
        $post = Post::create(['title' => 'Gone']);
        $snapshot = PostEditor::make(['post' => $post, 'related' => Post::query()->whereRaw('0 = 1')->get()])->snapshot();
        $post->delete();

        $this->withHeaders(['HX-Request' => 'true'])
            ->post('/lamx/lamx-test::post-editor/rename', ['title' => 'x', HtmxComponent::STATE_KEY => $snapshot])
            ->assertNotFound();
    }
}
