<?php

namespace Xlited\Lamx\Attributes;

use Attribute;

/**
 * Marks a public property as shared with Alpine.js: it is rendered into the
 * root element's x-data and the browser may send a new value back with every
 * request. Only int, float, string, bool (nullable or not) and flat arrays of
 * scalars can be bindable.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Bindable
{
    /**
     * @param  string|null  $as  The name Alpine sees, when it differs from the property name.
     */
    public function __construct(public ?string $as = null) {}
}
