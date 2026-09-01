<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Action Route
    |--------------------------------------------------------------------------
    |
    | Component actions are dispatched through a single route:
    | {prefix}/{component}/{action}. The middleware group must provide the
    | session and CSRF protection, so "web" is the sensible default.
    |
    */

    'route' => [
        'prefix' => 'lamx',
        'middleware' => ['web'],
        'name' => 'lamx.action',
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Layout
    |--------------------------------------------------------------------------
    |
    | Used when a component is rendered as a full page (Route::get('/', Foo::class)).
    | "type" may be "extends" (@extends / @section layout), "component"
    | (<x-layout> with a $slot) or "auto" to detect it from the view name.
    |
    */

    'layout' => [
        'view' => 'layouts.app',
        'type' => 'auto',
        'section' => 'content',
    ],

    /*
    |--------------------------------------------------------------------------
    | View Prefix
    |--------------------------------------------------------------------------
    |
    | When a component does not define $view, its view name is guessed from
    | the component name: App\View\Components\TodoForm => "components.todo-form".
    |
    */

    'view_prefix' => 'components.',

];
