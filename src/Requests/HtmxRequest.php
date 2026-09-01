<?php

namespace Xlited\Lamx\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Xlited\Lamx\Components\HtmxComponent;
use Xlited\Lamx\LamxFacade as Lamx;

/**
 * A form request that, when validation fails during an htmx request,
 * responds with the given component re-rendered with the errors instead
 * of redirecting back.
 */
class HtmxRequest extends FormRequest
{
    /**
     * The component class rendered when validation fails.
     *
     * @var class-string<HtmxComponent>
     */
    public string $component;

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        if (isset($this->component) && Lamx::isHtmxRequest($this)) {
            $component = $this->component::make()->withErrors($validator->errors());

            throw new ValidationException($validator, $component->toResponse($this));
        }

        parent::failedValidation($validator);
    }
}
