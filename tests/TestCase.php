<?php

namespace Xlited\Lamx\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;
use Xlited\Lamx\LamxServiceProvider;
use Xlited\Lamx\Tests\Fixtures\Components\Counter;
use Xlited\Lamx\Tests\Fixtures\Components\Greeting;
use Xlited\Lamx\Tests\Fixtures\Components\Page;
use Xlited\Lamx\Tests\Fixtures\Components\Search;
use Xlited\Lamx\Tests\Fixtures\Components\TodoForm;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LamxServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Blade::component('counter', Counter::class);
        Blade::component('greeting', Greeting::class);
        Blade::component('page', Page::class);
        Blade::component('search', Search::class);
        Blade::component('todo-form', TodoForm::class);
        Blade::componentNamespace('Xlited\\Lamx\\Tests\\Fixtures\\Components', 'lamx-test');

        View::addNamespace('lamx-test', __DIR__.'/Fixtures/views/lamx-test');
    }
}
