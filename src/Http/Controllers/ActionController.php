<?php

namespace Xlited\Lamx\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\LamxFacade as Lamx;

class ActionController
{
    public function __invoke(Request $request, string $component, string $action): Response
    {
        /** @var class-string<HtmxComponent> $class */
        $class = Lamx::componentClass($component);

        $snapshot = $request->input(HtmxComponent::STATE_KEY);

        $instance = is_string($snapshot) && $snapshot !== ''
            ? $class::fromSnapshot($snapshot)
            : $class::make();

        return $instance->dispatchAction($action, $request);
    }
}
