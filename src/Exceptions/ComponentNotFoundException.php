<?php

namespace Xlited\Lamx\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ComponentNotFoundException extends NotFoundHttpException
{
    public static function forName(string $name, ?Throwable $previous = null): static
    {
        return new static("Unable to find an htmx component named [{$name}].", $previous);
    }
}
