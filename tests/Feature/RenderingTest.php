<?php

namespace Xlited\Lamx\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Xlited\Lamx\Support\Snapshot;
use Xlited\Lamx\Tests\Fixtures\Components\Counter;
use Xlited\Lamx\Tests\Fixtures\Components\Greeting;
use Xlited\Lamx\Tests\Fixtures\Components\WithVals;
use Xlited\Lamx\Tests\TestCase;

class RenderingTest extends TestCase
{
    public function test_this_is_bound_inside_the_view(): void
    {
        $html = Counter::make(['count' => 3, 'label' => 'Clicks'])->toHtml();

        $this->assertStringContainsString('<span class="label">Clicks</span>', $html);
        $this->assertStringContainsString('<span class="count">3</span>', $html);
    }

    public function test_action_urls_point_to_the_action_route(): void
    {
        $html = Counter::make()->toHtml();

        $this->assertStringContainsString('hx-post="http://localhost/lamx/counter/increment"', $html);
        $this->assertStringContainsString('hx-post="http://localhost/lamx/counter/add?amount=5"', $html);
        $this->assertSame('http://localhost/lamx/counter/reset', Counter::actionUrl('reset'));
    }

    public function test_state_snapshot_is_attached_to_the_root_element(): void
    {
        $html = Counter::make(['count' => 7])->toHtml();

        $this->assertMatchesRegularExpression('/^<div hx-vals:inherited="[^"]+" id="counter">/', $html);

        preg_match('/hx-vals:inherited="([^"]+)"/', $html, $m);
        $vals = json_decode(html_entity_decode($m[1]), true);

        $payload = Snapshot::decode($vals['_lamx']);
        $this->assertSame(Counter::class, $payload['class']);
        $this->assertSame(['count' => 7, 'history' => [], 'label' => 'Count'], $payload['props']);
    }

    public function test_stateless_components_do_not_get_a_snapshot(): void
    {
        $this->assertSame("<p>Hello Ion</p>\n", Greeting::make(['name' => 'Ion'])->toHtml());
    }

    public function test_existing_hx_vals_on_the_root_are_merged(): void
    {
        $html = WithVals::make(['vals' => '{"a": 1}', 'n' => 2])->toHtml();

        preg_match('/hx-vals:inherited="([^"]+)"/', $html, $m);
        $vals = json_decode(html_entity_decode($m[1]), true);

        $this->assertSame(1, $vals['a']);
        $this->assertArrayHasKey('_lamx', $vals);
        $this->assertStringNotContainsString("hx-vals='", $html);
        $this->assertStringContainsString('id="x">2</div>', $html);

        $html = WithVals::make(['vals' => 'js:{q: document.title}', 'n' => 3])->toHtml();
        $this->assertMatchesRegularExpression('/hx-vals:inherited="js:\{&quot;_lamx&quot;: &quot;[^&]+&quot;, q: document\.title\}"/', $html);
    }

    public function test_components_render_through_blade_tags_with_slots_and_attributes(): void
    {
        $html = view('wrapper', ['count' => 4])->render();

        $this->assertStringContainsString('<span class="count">4</span>', $html);
        $this->assertStringContainsString('inside slot', $html);
        $this->assertStringContainsString('hx-vals:inherited=', $html);
        $this->assertStringContainsString('hx-post="http://localhost/lamx/counter/increment"', $html);
    }

    public function test_components_are_responsable_and_stringable(): void
    {
        $component = Counter::make(['count' => 9]);

        $this->assertStringContainsString('<span class="count">9</span>', (string) $component);
        $this->assertStringContainsString('<span class="count">9</span>', $component->toResponse(request())->getContent());
    }

    public function test_view_name_is_guessed_from_namespaced_component_names(): void
    {
        $this->assertSame('lamx-test::with-vals', WithVals::componentName());
        $this->assertSame('counter', Counter::componentName());
    }

    public function test_inline_blade_and_nested_components_keep_their_own_this(): void
    {
        $html = Blade::render('<x-greeting name="A" /><x-counter :count="1" /><x-greeting name="B" />');

        $this->assertStringContainsString('Hello A', $html);
        $this->assertStringContainsString('<span class="count">1</span>', $html);
        $this->assertStringContainsString('Hello B', $html);
    }
}
