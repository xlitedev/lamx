<?php

namespace Xlited\Lamx\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use LogicException;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Tests\Fixtures\Components\BadlyBound;
use Xlited\Lamx\Tests\Fixtures\Components\Search;
use Xlited\Lamx\Tests\Fixtures\Components\StatelessSearch;
use Xlited\Lamx\Tests\Fixtures\Components\WithService;
use Xlited\Lamx\Tests\TestCase;

class BindingTest extends TestCase
{
    protected function callWithBindings(string $action, array|string $bindings = [], ?Search $state = null)
    {
        $state ??= Search::make();

        return $this->withHeaders(['HX-Request' => 'true'])->post("/lamx/search/{$action}", [
            HtmxComponent::STATE_KEY => $state->snapshot(),
            HtmxComponent::BINDING_KEY => is_string($bindings) ? $bindings : json_encode($bindings),
        ]);
    }

    protected function xData(string $html): array
    {
        preg_match('/x-data="([^"]+)"/', $html, $m);

        return json_decode(html_entity_decode($m[1]), true);
    }

    public function test_bindable_properties_are_rendered_into_x_data(): void
    {
        $search = Search::make();
        $search->query = 'milk';
        $search->page = 2;
        $search->tags = ['a', 'b'];

        $html = $search->toHtml();

        $this->assertMatchesRegularExpression('/^<div x-data="[^"]+" hx-vals:inherited="[^"]+" id="search">/', $html);
        $this->assertSame(
            ['query' => 'milk', 'page' => 2, 'exact' => false, 'ratio' => 1.0, 'tags' => ['a', 'b'], 'sortBy' => 'name'],
            $this->xData($html)
        );
    }

    public function test_bound_values_are_coerced_and_applied_before_the_action(): void
    {
        $response = $this->callWithBindings('search', [
            'query' => 'milk', 'page' => '3', 'exact' => true, 'ratio' => '2.5', 'tags' => ['x', 1],
            'sortBy' => 'date', 'secret' => 'hacked', 'open' => false,
        ]);

        $response->assertOk();
        $response->assertSee('milk|3|true|2.5|x,1|date|server|query:>milk;page:null>3;searched');
        $this->assertSame('milk', $this->xData($response->getContent())['query']);
    }

    public function test_unchanged_values_do_not_fire_hooks_and_unknown_keys_are_ignored(): void
    {
        $this->callWithBindings('search', ['query' => '', 'secret' => 'x', 'nope' => 1])
            ->assertOk()
            ->assertSee('|server|searched</div>', false);
    }

    public function test_an_empty_string_clears_nullable_properties(): void
    {
        $search = Search::make();
        $search->page = 2;

        $this->callWithBindings('search', ['page' => ''], $search)
            ->assertOk()
            ->assertSee('|null|false|1|', false)
            ->assertSee('page:2>null;searched');
    }

    public function test_uncoercible_values_are_rejected(): void
    {
        $this->callWithBindings('search', ['page' => 'abc'])->assertStatus(400);
        $this->callWithBindings('search', ['exact' => 'maybe'])->assertStatus(400);
        $this->callWithBindings('search', ['query' => null])->assertStatus(400);
        $this->callWithBindings('search', ['tags' => 'x'])->assertStatus(400);
        $this->callWithBindings('search', ['tags' => [[1]]])->assertStatus(400);
        $this->callWithBindings('search', 'not json')->assertStatus(400);
        $this->callWithBindings('search', '')->assertOk();
    }

    public function test_the_refresh_action_re_renders_with_the_bound_values(): void
    {
        $this->assertSame('http://localhost/lamx/search/$refresh', Search::actionUrl('$refresh'));

        $this->callWithBindings('$refresh', ['query' => 'q', 'page' => 4])
            ->assertOk()
            ->assertSee('q|4|false|1||name|server|query:>q;page:null>4');
    }

    public function test_updated_hooks_are_not_actions(): void
    {
        $this->assertFalse(Search::hasAction('updatedPage'));
        $this->assertFalse(Search::hasAction('updatedQuery'));
        $this->assertTrue(Search::hasAction('search'));

        $this->callWithBindings('updatedPage')->assertNotFound();
    }

    public function test_an_existing_x_data_literal_on_the_root_is_extended(): void
    {
        $html = Search::make(['rootData' => '{ open: false, query: "local" }'])->toHtml();

        $this->assertMatchesRegularExpression(
            '/x-data="\{open: false, query: &quot;local&quot;, &quot;query&quot;: &quot;&quot;, &quot;page&quot;: null, /', $html
        );

        $this->expectException(LogicException::class);
        Search::make(['rootData' => 'dropdown'])->toHtml();
    }

    public function test_bindable_properties_require_state_and_scalar_types(): void
    {
        try {
            StatelessSearch::make()->toHtml();
            $this->fail('Stateless components must not accept bindable properties.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot be stateless', $e->getMessage());
        }

        $this->expectException(LogicException::class);
        BadlyBound::make()->toHtml();
    }

    public function test_the_scripts_send_the_alpine_scope_with_every_request(): void
    {
        $script = Blade::render('@lamxScripts');

        $this->assertStringContainsString("'_lamx_data'", $script);
        $this->assertStringContainsString('Alpine.$data(root)', $script);
        $this->assertStringContainsString("closest('[x-data][hx-vals\\\\:inherited]')", $script);
    }

    public function test_dropped_properties_are_logged_unless_the_constructor_recreates_them(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message) => str_contains($message, '$meta'));

        $this->assertSame('<div>ok</div>', WithService::make()->toHtml());
    }
}
