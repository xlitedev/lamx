<?php

namespace Xlited\Lamx\Features;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Renders a component as a full page, inside a layout. Register the
 * component as a route action: Route::get('/', Todos::class).
 *
 * Two kinds of layout are supported: classic @extends layouts with a
 *
 * @yield section and component layouts (<x-layouts.app>) with a $slot.
 */
trait HandlesPageComponents
{
    /**
     * The layout view, e.g. "layouts.app". Defaults to config('lamx.layout.view').
     */
    protected string $layout = '';

    /**
     * "extends", "component" or "auto". Defaults to config('lamx.layout.type').
     */
    protected string $layoutType = '';

    public function __invoke(): Response
    {
        return new Response($this->renderPage());
    }

    /**
     * Render the component inside its layout.
     */
    public function renderPage(): string
    {
        $layout = $this->getLayout();
        $content = new HtmlString($this->toHtml());

        return $this->resolveLayoutType($layout) === 'component'
            ? $this->renderComponentLayout($layout, $this->layoutParams(), $content)
            : $this->renderExtendsLayout($layout, $this->layoutParams(), $content);
    }

    protected function getLayout(): string
    {
        return $this->layout ?: config('lamx.layout.view', 'layouts.app');
    }

    /**
     * Data passed to the layout.
     */
    protected function layoutParams(): array
    {
        return [];
    }

    /**
     * The @yield section filled with the component in "extends" layouts.
     */
    protected function layoutSection(): string
    {
        return config('lamx.layout.section', 'content');
    }

    protected function layoutType(): string
    {
        return $this->layoutType ?: config('lamx.layout.type', 'auto');
    }

    protected function resolveLayoutType(string $layout): string
    {
        $type = $this->layoutType();

        if ($type === 'auto') {
            $type = view()->exists($this->layoutComponentView($layout)) ? 'component' : 'extends';
        }

        if (! in_array($type, ['extends', 'component'], true)) {
            throw new InvalidArgumentException("Unknown layout type [{$type}]. Use \"extends\", \"component\" or \"auto\".");
        }

        return $type;
    }

    /**
     * The view name a component layout would have ("layouts.app" => "components.layouts.app").
     */
    protected function layoutComponentView(string $layout): string
    {
        return str_starts_with($layout, 'components.') ? $layout : 'components.'.$layout;
    }

    protected function renderExtendsLayout(string $layout, array $params, HtmlString $content): string
    {
        return Blade::render(<<<'BLADE'
            @extends($__lamx->layout, $__lamx->params)

            @section($__lamx->section)
                {!! $__lamx->content !!}
            @endsection
            BLADE, [
            '__lamx' => (object) [
                'layout' => $layout,
                'params' => $params,
                'section' => $this->layoutSection(),
                'content' => $content,
            ],
        ]);
    }

    protected function renderComponentLayout(string $layout, array $params, HtmlString $content): string
    {
        $name = str_starts_with($layout, 'components.') ? substr($layout, strlen('components.')) : $layout;

        $bindings = '';

        foreach (array_keys($params) as $key) {
            if (! preg_match('/^[a-zA-Z][\w-]*$/', $key)) {
                throw new InvalidArgumentException("Invalid layout parameter name [{$key}].");
            }

            $bindings .= ' :'.$key.'="$__lamx->params[\''.$key.'\']"';
        }

        return Blade::render(
            '<x-dynamic-component :component="$__lamx->layout"'.$bindings.'>{!! $__lamx->content !!}</x-dynamic-component>',
            ['__lamx' => (object) ['layout' => $name, 'params' => $params, 'content' => $content]]
        );
    }
}
