# Lamx - Build Laravel apps with </> htmx

[![Latest Version on Packagist](https://img.shields.io/packagist/v/xlited/lamx.svg?style=flat-square)](https://packagist.org/packages/xlited/lamx)
[![Total Downloads](https://img.shields.io/packagist/dt/xlited/lamx.svg?style=flat-square)](https://packagist.org/packages/xlited/lamx)
![GitHub Actions](https://github.com/xlited/lamx/actions/workflows/main.yml/badge.svg)

**Lamx** brings Livewire-style components to [htmx](https://htmx.org). A component is a single PHP
class that holds its **state** (public properties), its **actions** (public methods) and its
**validation** rules, plus a Blade view in which `$this` is available. htmx does the transport:
every request made from inside a component carries a signed snapshot of its state, the action
runs against the re-hydrated component and the returned HTML is swapped into the page.

Requires PHP 8.1+, Laravel 10–13 and htmx 4.

## Installation

```bash
composer require xlited/lamx
```

Load htmx and the Lamx helpers in your layout:

```blade
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://unpkg.com/htmx.org@4.0.0/dist/htmx.min.js"></script>
</head>
<body>
    @yield('content')

    @lamxTemplates {{-- the error modal (daisyUI markup) --}}
    @lamxScripts   {{-- sends the CSRF token, shows 5xx responses in the modal --}}
</body>
```

Optionally publish the config: `php artisan vendor:publish --tag=lamx-config`.

## A component

```php
namespace App\View\Components;

use Xlited\Lamx\Components\HtmxComponent;

class Todo extends HtmxComponent
{
    public function __construct(public int $id, public string $title, public bool $done = false)
    {
    }

    // Actions are public methods. They run against the component as it was rendered.
    public function toggle(): void
    {
        $this->done = ! $this->done;

        Todo::where('id', $this->id)->update(['done' => $this->done]);
    }
}
```

```blade
{{-- resources/views/components/todo.blade.php --}}
<div id="todo-{{ $this->id }}" @class(['line-through' => $this->done])>
    <input type="checkbox" @checked($this->done)
           hx-post="{{ $this->action('toggle') }}" hx-target="closest div" hx-swap="outerHTML">
    {{ $this->title }}
</div>
```

Use it like any Blade component (`<x-todo :id="1" title="Milk" />`), build it in PHP
(`Todo::make(['id' => 1, 'title' => 'Milk'])`) or return it from a route or controller — it is
`Responsable`, `Htmlable` and `Stringable`.

### State

The public properties are the state. When the component renders, a signed snapshot of them is
attached to the root element as `hx-vals:inherited`, so htmx sends it with every request made
from inside the component and the action runs on an identical instance. Scalars, arrays, enums,
dates, collections and Eloquent models (stored by key, re-fetched on the next request) are
supported; anything else is left out and re-created by the constructor.

- Set `protected bool $stateless = true;` to skip the snapshot.
- If an element inside the component needs its own `hx-vals`, use `hx-vals:append` so the
  component state is kept (htmx 4 replaces the whole value otherwise).
- The snapshot is signed with `APP_KEY`; tampering yields a `400`.

### Actions

`$this->action('name')` (or `Todo::actionUrl('name')`) returns the URL of the action:
`/lamx/{component}/{action}`. Component names follow Blade's `<x-name>` rules — classes under
`App\View\Components`, `Blade::component()` aliases and `Blade::componentNamespace()` prefixes.

Action arguments are resolved by name from the request input (scalar parameters) and from the
container (class-typed parameters), so `public function add(int $amount, Request $request)`
just works. Extra parameters given to `$this->action('add', ['amount' => 5])` become a query
string.

An action may return:

| return                                | response                                          |
| ------------------------------------- | ------------------------------------------------- |
| nothing                               | the component, re-rendered                        |
| a component, view, `Htmlable`, string | rendered as is                                    |
| an array of those                     | rendered one after another (use `hx-swap-oob`)    |
| `redirect(...)`                       | `HX-Redirect` header (a plain redirect otherwise) |
| any response                          | as is                                             |

Only public methods declared by your component are callable; the framework's own methods
(`render`, `validate`, `toHtml`...) are not.

### Validation

```php
class TodoForm extends HtmxComponent
{
    protected function rules(): array
    {
        return ['title' => 'required|max:255'];
    }

    public function save(TodoStore $store): array
    {
        $todo = $store->add($this->validate()['title']);

        return [static::make(), Todo::make($todo)];
    }
}
```

`$this->validate()` validates the request input against `rules()` (also `messages()`,
`validationAttributes()`, or pass them explicitly). When it fails, the component is re-rendered
with `$errors` available in the view and `old()` filled with the submitted input — a classic
Laravel form, without the redirect. Form requests injected into actions behave the same way;
`Xlited\Lamx\Requests\HtmxRequest` does it for plain controllers too.

### Pages

```php
Route::get('/', Todos::class);
```

A component used as a route action is rendered inside a layout: `@extends` layouts with a
`@yield('content')` section and component layouts (`<x-layouts.app>` with a `$slot`) are both
supported and detected automatically. Configure the default in `config/lamx.php` or per
component with `protected string $layout` and `layoutParams()`.

### Views

`$view` defaults to `components.<component-name>`; set `protected string $view` or override
`render()` (a view, an `Htmlable`, an inline Blade string or a closure) for full control. Inside
the view `$this` is the component, and public properties are also available as plain variables,
as in any Blade component.

## The demo

A complete Todo app built with Lamx: https://github.com/icaliman/laravel-htmx

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email xlite.dev@gmail.com instead of using the issue tracker.

## Credits

-   [Ion Caliman](https://github.com/xlited)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
