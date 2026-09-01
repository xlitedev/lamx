# Changelog

All notable changes to `lamx` will be documented in this file

## 0.2.0 - 2026-09-01

- Livewire-style components: public properties are state, carried between requests in an
  encrypted snapshot (`hx-vals:inherited` on the root element); public methods are actions
  dispatched through `/lamx/{component}/{action}`.
- Alpine.js binding: `#[Bindable]` properties are rendered into the root element's `x-data` and
  sent back with every request (`_lamx_data`), coerced to the property type and applied before
  the action runs. `updated{Property}($value, $old)` hooks, `#[Bindable(as: 'name')]` aliases,
  merging into an existing `x-data="{ ... }"` literal, and the built-in `$refresh` action.
- Public properties that cannot be stored in the snapshot are logged as a warning, unless the
  constructor re-creates them.
- `$this` is available inside component views; `$this->action('name')` builds action URLs.
- Validation inside actions (`rules()`, `$this->validate()`), errors re-render the component.
- Actions may return components, arrays of components, views, strings, redirects or responses.
- Page components support `@extends` and component layouts (auto-detected).
- htmx 4: `@lamxScripts` uses the `htmx:config:request` / `htmx:before:swap` events.
- Laravel 11, 12 and 13 support.

## 0.0.1 - 2024-03-13

- initial release
