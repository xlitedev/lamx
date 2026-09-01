<?php

namespace Xlited\Lamx\Exceptions;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvalidBindingException extends BadRequestHttpException
{
    public static function forProperty(string $property, string $reason): static
    {
        return new static("The value bound to [{$property}] {$reason}.");
    }
}
