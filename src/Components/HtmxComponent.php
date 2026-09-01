<?php

namespace Xlited\Lamx\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Xlited\Lamx\Features\HandlesActions;
use Xlited\Lamx\Features\HandlesBinding;
use Xlited\Lamx\Features\HandlesPageComponents;
use Xlited\Lamx\Features\HandlesState;
use Xlited\Lamx\Features\HandlesValidation;
use Xlited\Lamx\LamxFacade as Lamx;
use Xlited\Lamx\Support\RootElement;

/**
 * An interactive, htmx driven Blade component.
 *
 * Everything that belongs to a piece of UI lives in one class:
 *
 *  - state:      public properties, carried between requests in an encrypted snapshot
 *  - binding:    #[Bindable] properties are shared with Alpine.js (x-data / x-model)
 *  - actions:    public methods, invoked through /lamx/{component}/{action}
 *  - validation: rules() + $this->validate(), errors re-render the component
 *  - views:      $this is available inside the component's Blade view
 *  - pages:      Route::get('/', MyComponent::class) renders it inside a layout
 *
 * A component can be rendered with <x-name />, Name::make([...]), returned
 * from a route/controller (it is Responsable) or from another action.
 */
abstract class HtmxComponent extends Component implements Htmlable, Responsable
{
    use HandlesActions;
    use HandlesBinding;
    use HandlesPageComponents;
    use HandlesState;
    use HandlesValidation;

    /**
     * The request field that carries the component snapshot.
     */
    public const STATE_KEY = '_lamx';

    /**
     * The request field that carries the values bound in the browser.
     */
    public const BINDING_KEY = '_lamx_data';

    /**
     * The built-in action that only re-renders the component.
     */
    public const REFRESH_ACTION = '$refresh';

    /**
     * The view rendered by the component. Guessed from the component name
     * when empty: App\View\Components\TodoForm => "components.todo-form".
     */
    protected string $view = '';

    /**
     * Create a new component instance, resolving constructor arguments
     * from the given data and the container.
     */
    public static function make(array $data = []): static
    {
        return static::resolve($data);
    }

    /**
     * Request fields that belong to Lamx or Laravel, never to an action.
     *
     * @return array<int, string>
     */
    public static function reservedInput(): array
    {
        return [static::STATE_KEY, static::BINDING_KEY, '_token', '_method'];
    }

    /**
     * The view that represents the component. Override for full control.
     */
    public function render(): View|Htmlable|Closure|string
    {
        return view($this->viewName());
    }

    /**
     * Render the component to an HTML string.
     */
    public function toHtml(): string
    {
        return $this->renderWithData($this->data() + ['slot' => new ComponentSlot]);
    }

    /**
     * Create an HTTP response for the component.
     */
    public function toResponse($request): Response
    {
        return new Response($this->toHtml());
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Resolve the view for Blade's component pipeline (<x-name />). The
     * closure receives the full component data, including slots.
     */
    public function resolveView()
    {
        return fn (array $data = []) => new HtmlString($this->renderWithData($data));
    }

    /**
     * The name of the component as used in <x-name />.
     */
    public static function componentName(): string
    {
        return Lamx::componentName(static::class);
    }

    protected function viewName(): string
    {
        if ($this->view !== '') {
            return $this->view;
        }

        $name = static::componentName();
        $prefix = config('lamx.view_prefix', 'components.');

        return str_contains($name, '::')
            ? Str::before($name, '::').'::'.$prefix.Str::after($name, '::')
            : $prefix.$name;
    }

    /**
     * Render the component's view with the given data, with $this bound
     * inside the view, and attach the state snapshot to the root element.
     */
    protected function renderWithData(array $data): string
    {
        return Lamx::withComponent($this, function () use ($data) {
            $data = $this->prepareViewData($data);

            $view = value(parent::resolveView(), $data);

            $html = match (true) {
                $view instanceof View => $view->with($data)->render(),
                $view instanceof Htmlable => $view->toHtml(),
                default => view($view, $data)->render(),
            };

            return $this->decorateHtml($html);
        });
    }

    /**
     * Make sure $errors is always available inside the view.
     */
    protected function prepareViewData(array $data): array
    {
        if (! array_key_exists('errors', $data)) {
            $data['errors'] = $this->errors() ?? view()->shared('errors', new ViewErrorBag);
        }

        return $data;
    }

    /**
     * Attach the state snapshot and the bindable properties (Alpine's
     * x-data) to the root element of the rendered HTML.
     */
    protected function decorateHtml(string $html): string
    {
        $snapshot = $this->snapshotIfStateful();
        $data = $this->bindableData();

        if ($data !== [] && $snapshot === null) {
            throw new LogicException(
                '['.static::class.'] has bindable properties, so it cannot be stateless: '
                .'the state snapshot is what identifies the component when the bound values come back.'
            );
        }

        if ($snapshot !== null) {
            $html = RootElement::injectVals($html, [static::STATE_KEY => $snapshot]);
        }

        if ($data !== []) {
            $html = RootElement::injectData($html, $data);
        }

        return $html;
    }

    /**
     * The framework's own public methods are not exposed to the view as
     * variables, nor callable as actions.
     */
    protected function ignoredMethods()
    {
        return array_merge(parent::ignoredMethods(), static::frameworkMethods());
    }

    /**
     * Public methods declared by the base component (and its traits).
     *
     * @return array<int, string>
     */
    public static function frameworkMethods(): array
    {
        static $methods = null;

        return $methods ??= array_map(
            fn (ReflectionMethod $method) => $method->getName(),
            (new ReflectionClass(self::class))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
    }
}
