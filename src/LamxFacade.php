<?php

namespace Xlited\Lamx;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Xlited\Lamx\Components\HtmxComponent|null current()
 * @method static mixed withComponent(\Xlited\Lamx\Components\HtmxComponent $component, \Closure $callback)
 * @method static string componentClass(string $name)
 * @method static string componentName(string $class)
 * @method static string actionUrl(string $class, string $action, array $parameters = [])
 * @method static string render(mixed $content)
 * @method static bool isHtmxRequest(?\Illuminate\Http\Request $request = null)
 * @method static bool isBoostedRequest(?\Illuminate\Http\Request $request = null)
 *
 * @see Lamx
 */
class LamxFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'lamx';
    }
}
