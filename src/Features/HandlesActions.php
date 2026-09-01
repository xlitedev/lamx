<?php

namespace Xlited\Lamx\Features;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\Exceptions\ActionNotFoundException;
use Xlited\Lamx\LamxFacade as Lamx;

/**
 * Public methods of a component are its actions. They are invoked from the
 * browser through hx-get/hx-post/... = $this->action('name') and may return:
 *
 *  - nothing: the component is re-rendered (the usual case)
 *  - a component, a view, an Htmlable or a string
 *  - an array of those: rendered one after another (use hx-swap-oob)
 *  - a redirect: converted to an HX-Redirect header for htmx requests
 *  - any response
 *
 * Method arguments are resolved from the request input by name (scalar
 * parameters) and from the container (class-typed parameters).
 */
trait HandlesActions
{
    /**
     * The URL that invokes the given action on this component.
     */
    public function action(string $action, array $parameters = []): string
    {
        return static::actionUrl($action, $parameters);
    }

    /**
     * The URL that invokes the given action on this component class.
     */
    public static function actionUrl(string $action, array $parameters = []): string
    {
        return Lamx::actionUrl(static::class, $action, $parameters);
    }

    /**
     * Determine if the given method is an action of this component.
     */
    public static function hasAction(string $action): bool
    {
        if (str_starts_with($action, '__')
            || method_exists(HtmxComponent::class, $action)
            || ! method_exists(static::class, $action)) {
            return false;
        }

        $method = new ReflectionMethod(static::class, $action);

        return $method->isPublic() && ! $method->isStatic();
    }

    /**
     * Invoke an action and convert its result into an HTTP response.
     */
    public function dispatchAction(string $action, Request $request): Response
    {
        if (! static::hasAction($action)) {
            throw ActionNotFoundException::forAction(static::class, $action);
        }

        try {
            $result = Container::getInstance()->call([$this, $action], $this->actionParameters($action, $request));
        } catch (ValidationException $e) {
            // Make old() work in the re-rendered form, for this request only.
            if ($request->hasSession()) {
                $request->session()->now('_old_input', $request->except([HtmxComponent::STATE_KEY, '_token', '_method']));
            }

            $this->withErrors($e->validator->errors(), $e->errorBag);

            $result = null;
        }

        return $this->toActionResponse($result, $request);
    }

    /**
     * Collect scalar method arguments from the route and the request input.
     */
    protected function actionParameters(string $action, Request $request): array
    {
        $input = array_merge(
            $request->except([HtmxComponent::STATE_KEY, '_token', '_method']),
            array_diff_key($request->route()?->parameters() ?? [], array_flip(['component', 'action']))
        );

        $parameters = [];

        foreach ((new ReflectionMethod($this, $action))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $parameter->getName();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                continue; // resolved from the container
            }

            if (array_key_exists($name, $input)) {
                $parameters[$name] = $input[$name];
            }
        }

        return $parameters;
    }

    /**
     * Normalise whatever an action returned into a response.
     */
    protected function toActionResponse(mixed $result, Request $request): Response
    {
        $result ??= $this;

        if ($result instanceof RedirectResponse && Lamx::isHtmxRequest($request) && ! Lamx::isBoostedRequest($request)) {
            return response('', 200, ['HX-Redirect' => $result->getTargetUrl()]);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof Responsable) {
            return $result->toResponse($request);
        }

        if ($result instanceof Htmlable || $result instanceof Renderable || is_iterable($result) || is_string($result)) {
            return response(Lamx::render($result));
        }

        return Router::toResponse($request, $result);
    }
}
