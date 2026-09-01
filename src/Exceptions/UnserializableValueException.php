<?php

namespace Xlited\Lamx\Exceptions;

use RuntimeException;

/**
 * Thrown internally when a property value cannot be carried in a snapshot.
 * Such properties are simply left out and re-created by the constructor.
 */
class UnserializableValueException extends RuntimeException {}
