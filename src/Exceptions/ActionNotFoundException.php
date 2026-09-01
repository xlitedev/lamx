<?php

namespace Xlited\Lamx\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActionNotFoundException extends NotFoundHttpException
{
    public static function forAction(string $class, string $action): static
    {
        return new static("Action [{$action}] is not a public method of component [{$class}].");
    }
}
