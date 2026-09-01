# Changelog

All notable changes to `lamx` will be documented in this file

## Unreleased

- Livewire-style components: public properties are state, carried between requests in a signed
  snapshot (`hx-vals:inherited` on the root element); public methods are actions dispatched
  through `/lamx/{component}/{action}`.
- `$this` is available inside component views; `$this->action('name')` builds action URLs.
- Validation inside actions (`rules()`, `$this->validate()`), errors re-render the component.
- Actions may return components, arrays of components, views, strings, redirects or responses.
- Page components support `@extends` and component layouts (auto-detected).
- htmx 4: `@lamxScripts` uses the `htmx:config:request` / `htmx:before:swap` events.
- Laravel 11, 12 and 13 support.

## 0.0.1 - 2024-03-13

- initial release
