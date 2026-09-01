<?php

namespace Xlited\Lamx\Features;

use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/**
 * Validation inside actions. $this->validate() validates the request input
 * against rules(); when it fails the component is re-rendered with the
 * errors available as $errors in the view, exactly like a classic form.
 */
trait HandlesValidation
{
    protected ?ViewErrorBag $viewErrors = null;

    /**
     * The validation rules applied by validate().
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Custom validation messages.
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Custom attribute names for validation messages.
     */
    protected function validationAttributes(): array
    {
        return [];
    }

    /**
     * The data being validated.
     */
    protected function validationData(): array
    {
        return request()->all();
    }

    /**
     * Validate the request input and return the validated data.
     *
     * @throws ValidationException
     */
    public function validate(?array $rules = null, ?array $messages = null, ?array $attributes = null): array
    {
        return $this->validator($rules, $messages, $attributes)->validate();
    }

    /**
     * A validator instance for the request input.
     */
    public function validator(?array $rules = null, ?array $messages = null, ?array $attributes = null): Validator
    {
        return ValidatorFactory::make(
            $this->validationData(),
            $rules ?? $this->rules(),
            $messages ?? $this->messages(),
            $attributes ?? $this->validationAttributes()
        );
    }

    /**
     * Attach validation errors to be rendered with the component.
     */
    public function withErrors(MessageProvider|MessageBagContract|array $errors, string $bag = 'default'): static
    {
        $messages = match (true) {
            $errors instanceof MessageBagContract => $errors,
            $errors instanceof MessageProvider => $errors->getMessageBag(),
            default => new MessageBag($errors),
        };

        $this->viewErrors ??= new ViewErrorBag;
        $this->viewErrors->put($bag ?: 'default', $messages);

        return $this;
    }

    /**
     * The errors attached to the component, if any.
     */
    public function errors(): ?ViewErrorBag
    {
        return $this->viewErrors;
    }
}
