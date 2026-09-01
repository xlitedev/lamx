<?php

namespace Xlited\Lamx\Tests\Feature;

use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Support\Snapshot;
use Xlited\Lamx\Tests\Fixtures\Components\Counter;
use Xlited\Lamx\Tests\Fixtures\Components\TodoForm;
use Xlited\Lamx\Tests\TestCase;

class ActionsTest extends TestCase
{
    protected function callAction(string $component, string $action, ?HtmxComponent $state = null, array $data = [], string $method = 'post')
    {
        if ($state) {
            $data[HtmxComponent::STATE_KEY] = $state->snapshot();
        }

        return $this->withHeaders(['HX-Request' => 'true'])->{$method}("/lamx/{$component}/{$action}", $data);
    }

    public function test_an_action_re_renders_the_hydrated_component(): void
    {
        $response = $this->callAction('counter', 'increment', Counter::make(['count' => 4, 'label' => 'Hits']));

        $response->assertOk();
        $response->assertSee('<span class="count">5</span>', false);
        $response->assertSee('<span class="label">Hits</span>', false);

        preg_match('/hx-vals:inherited="([^"]+)"/', $response->getContent(), $m);
        $props = Snapshot::decode(json_decode(html_entity_decode($m[1]), true)['_lamx'])['props'];
        $this->assertSame(['count' => 5, 'history' => [4], 'label' => 'Hits'], $props);
    }

    public function test_non_constructor_properties_are_hydrated_too(): void
    {
        $counter = Counter::make(['count' => 1]);
        $counter->history = [8, 9];

        $response = $this->callAction('counter', 'increment', $counter);

        $props = Snapshot::decode(json_decode(html_entity_decode(
            preg_match('/hx-vals:inherited="([^"]+)"/', $response->getContent(), $m) ? $m[1] : ''
        ), true)['_lamx'])['props'];

        $this->assertSame([8, 9, 1], $props['history']);
    }

    public function test_scalar_arguments_come_from_the_request_and_classes_from_the_container(): void
    {
        $response = $this->callAction('counter', 'add', Counter::make(['count' => 1]), ['amount' => 10, 'label' => 'Sum']);

        $response->assertOk()->assertSee('<span class="count">11</span>', false)->assertSee('<span class="label">Sum</span>', false);

        $response = $this->callAction('counter', 'add', Counter::make(['count' => 1]), [], 'get');
        $response->assertServerError();
    }

    public function test_query_string_arguments_work_for_get_requests(): void
    {
        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->get('/lamx/counter/add?amount=2&'.HtmxComponent::STATE_KEY.'='.urlencode(Counter::make(['count' => 1])->snapshot()));

        $response->assertOk()->assertSee('<span class="count">3</span>', false);
    }

    public function test_actions_may_return_components_arrays_strings_and_redirects(): void
    {
        $this->callAction('counter', 'reset', Counter::make(['count' => 5]))
            ->assertOk()->assertSee('<span class="count">0</span>', false)->assertSee('Fresh');

        $response = $this->callAction('counter', 'twice', Counter::make());
        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'id="counter"'));
        $response->assertSee('<span class="count">1</span>', false)->assertSee('<span class="count">2</span>', false);

        $this->callAction('counter', 'text', Counter::make(['count' => 3]))->assertOk()->assertSee('plain 3');

        $response = $this->callAction('counter', 'home', Counter::make());
        $response->assertOk()->assertHeader('HX-Redirect', 'http://localhost/home');
        $this->assertSame('', $response->getContent());

        $this->flushHeaders()->post('/lamx/counter/home')->assertRedirect('/home');
    }

    public function test_components_without_state_are_built_from_the_container(): void
    {
        $this->callAction('counter', 'increment')->assertOk()->assertSee('<span class="count">1</span>', false);
    }

    public function test_only_public_methods_of_the_component_itself_are_actions(): void
    {
        $this->callAction('counter', 'helper', Counter::make())->assertNotFound();
        $this->callAction('counter', 'render', Counter::make())->assertNotFound();
        $this->callAction('counter', 'validate', Counter::make())->assertNotFound();
        $this->callAction('counter', 'toHtml', Counter::make())->assertNotFound();
        $this->callAction('counter', '__construct', Counter::make())->assertNotFound();
        $this->callAction('counter', 'missing', Counter::make())->assertNotFound();
        $this->callAction('counter', 'secret', Counter::make())->assertOk();
    }

    public function test_unknown_components_are_not_found(): void
    {
        $this->callAction('nope', 'increment')->assertNotFound();
        $this->callAction('layouts.card', 'increment')->assertNotFound();
    }

    public function test_tampered_or_foreign_snapshots_are_rejected(): void
    {
        $snapshot = Counter::make(['count' => 1])->snapshot();

        $tampered = substr_replace($snapshot, $snapshot[20] === 'A' ? 'B' : 'A', 20, 1);

        $this->callAction('counter', 'increment', null, [HtmxComponent::STATE_KEY => $tampered])->assertStatus(400);
        $this->callAction('counter', 'increment', null, [HtmxComponent::STATE_KEY => 'garbage'])->assertStatus(400);
        $this->callAction('greeting', 'increment', null, [HtmxComponent::STATE_KEY => $snapshot])->assertStatus(400);
    }

    public function test_validation_errors_re_render_the_component(): void
    {
        $response = $this->callAction('todo-form', 'save', TodoForm::make(), ['title' => 'ab']);

        $response->assertOk();
        $response->assertSee('<span class="error">The title field must be at least 3 characters.</span>', false);
        $response->assertSee('value="ab"', false);

        $this->callAction('todo-form', 'save', TodoForm::make(), ['title' => 'abc'])
            ->assertOk()->assertSee('value="saved: abc"', false)->assertDontSee('class="error"');
    }

    public function test_the_action_route_is_configurable(): void
    {
        $this->assertSame('http://localhost/lamx/counter/increment', route('lamx.action', ['component' => 'counter', 'action' => 'increment']));
    }
}
