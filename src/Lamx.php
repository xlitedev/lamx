<?php

namespace Xlited\Lamx;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use InvalidArgumentException;
use LogicException;
use Throwable;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Exceptions\ComponentNotFoundException;

class Lamx
{
    /**
     * The components currently being rendered, innermost last.
     *
     * @var array<int, HtmxComponent>
     */
    protected array $renderStack = [];

    /** @var array<string, class-string<HtmxComponent>> */
    protected array $classCache = [];

    /** @var array<class-string<HtmxComponent>, string> */
    protected array $nameCache = [];

    public function __construct(protected Application $app) {}

    /**
     * The component whose view is currently being rendered, if any.
     */
    public function current(): ?HtmxComponent
    {
        return $this->renderStack === [] ? null : $this->renderStack[count($this->renderStack) - 1];
    }

    /**
     * Run the callback with the component marked as "currently rendering".
     */
    public function withComponent(HtmxComponent $component, Closure $callback): mixed
    {
        $this->renderStack[] = $component;

        try {
            return $callback();
        } finally {
            array_pop($this->renderStack);
        }
    }

    /**
     * Resolve a component name (as used in <x-name>) to its class.
     *
     * @return class-string<HtmxComponent>
     *
     * @throws ComponentNotFoundException
     */
    public function componentClass(string $name): string
    {
        if (isset($this->classCache[$name])) {
            return $this->classCache[$name];
        }

        try {
            $class = $this->tagCompiler()->componentClass($name);
        } catch (InvalidArgumentException $e) {
            throw ComponentNotFoundException::forName($name, $e);
        }

        if (! class_exists($class) || ! is_subclass_of($class, HtmxComponent::class)) {
            throw ComponentNotFoundException::forName($name);
        }

        return $this->classCache[$name] = $class;
    }

    /**
     * Determine the component name (as used in <x-name>) for a class.
     *
     * @param  class-string<HtmxComponent>  $class
     */
    public function componentName(string $class): string
    {
        $class = ltrim($class, '\\');

        if (isset($this->nameCache[$class])) {
            return $this->nameCache[$class];
        }

        $name = $this->guessComponentName($class);

        if ($name === null || $this->safeComponentClass($name) !== $class) {
            throw new LogicException(
                "Unable to determine a component name for [{$class}]. "
                ."Place it under the App\\View\\Components namespace or register it with Blade::component('name', {$class}::class)."
            );
        }

        return $this->nameCache[$class] = $name;
    }

    /**
     * The URL that invokes an action on the given component class.
     */
    public function actionUrl(string $class, string $action, array $parameters = []): string
    {
        return $this->app->make('url')->route(
            $this->app['config']->get('lamx.route.name', 'lamx.action'),
            ['component' => $this->componentName($class), 'action' => $action] + $parameters
        );
    }

    /**
     * Render anything a component action may return into an HTML string.
     */
    public function render(mixed $content): string
    {
        if ($content === null) {
            return '';
        }

        if ($content instanceof Htmlable) {
            return $content->toHtml();
        }

        if ($content instanceof Renderable) {
            return $content->render();
        }

        if (is_iterable($content)) {
            $html = '';

            foreach ($content as $item) {
                $html .= $this->render($item);
            }

            return $html;
        }

        return (string) $content;
    }

    /**
     * Determine if the request was made by htmx.
     */
    public function isHtmxRequest(?Request $request = null): bool
    {
        $request ??= $this->app->make('request');

        return $request->headers->has('HX-Request');
    }

    /**
     * Determine if the request was made by an htmx boosted link or form.
     */
    public function isBoostedRequest(?Request $request = null): bool
    {
        $request ??= $this->app->make('request');

        return $request->headers->has('HX-Boosted');
    }

    protected function guessComponentName(string $class): ?string
    {
        $blade = $this->blade();

        if (($alias = array_search($class, $blade->getClassComponentAliases(), true)) !== false) {
            return $alias;
        }

        foreach ($blade->getClassComponentNamespaces() as $prefix => $namespace) {
            $namespace = trim($namespace, '\\').'\\';

            if (str_starts_with($class, $namespace)) {
                return $prefix.'::'.$this->kebab(substr($class, strlen($namespace)));
            }
        }

        $namespace = $this->appNamespace().'View\\Components\\';

        if (str_starts_with($class, $namespace)) {
            return $this->kebab(substr($class, strlen($namespace)));
        }

        return null;
    }

    protected function safeComponentClass(string $name): ?string
    {
        try {
            return $this->componentClass($name);
        } catch (ComponentNotFoundException) {
            return null;
        }
    }

    protected function kebab(string $relativeClass): string
    {
        return implode('.', array_map(fn ($segment) => Str::kebab($segment), explode('\\', $relativeClass)));
    }

    protected function appNamespace(): string
    {
        try {
            return $this->app->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }

    protected function tagCompiler(): ComponentTagCompiler
    {
        $blade = $this->blade();

        return new ComponentTagCompiler($blade->getClassComponentAliases(), $blade->getClassComponentNamespaces(), $blade);
    }

    protected function blade(): BladeCompiler
    {
        return $this->app->make('blade.compiler');
    }
}
