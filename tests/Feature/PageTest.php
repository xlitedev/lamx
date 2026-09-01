<?php

namespace Xlited\Lamx\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Xlited\Lamx\Tests\Fixtures\Components\Page;
use Xlited\Lamx\Tests\TestCase;

class PageTest extends TestCase
{
    public function test_a_component_renders_as_a_page_inside_an_extends_layout(): void
    {
        Route::get('/page', Page::class)->middleware('web');

        $response = $this->get('/page');

        $response->assertOk();
        $response->assertSee('<title>Home</title>', false);
        $response->assertSee('Home page</main>', false);
    }

    public function test_a_component_renders_inside_a_component_layout(): void
    {
        config(['lamx.layout.view' => 'layouts.card']);

        $html = Page::make(['title' => 'Cards'])->renderPage();

        $this->assertStringContainsString('<section class="card" data-title="Cards">', $html);
        $this->assertStringContainsString('Cards page</main></section>', $html);
    }

    public function test_layout_type_can_be_forced(): void
    {
        config(['lamx.layout.view' => 'layouts.app', 'lamx.layout.type' => 'extends']);

        $this->assertStringContainsString('<title>Home</title>', Page::make()->renderPage());
    }
}
