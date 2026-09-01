<?php

namespace Xlited\Lamx\View;

use Illuminate\View\Engines\CompilerEngine;
use Throwable;
use Xlited\Lamx\LamxFacade as Lamx;

/**
 * Evaluates Blade views with $this bound to the component being rendered,
 * so component views can call $this->action('save'), $this->title, etc.
 */
class LamxCompilerEngine extends CompilerEngine
{
    protected function evaluatePath($path, $data)
    {
        $component = Lamx::current();

        if ($component === null) {
            return parent::evaluatePath($path, $data);
        }

        $obLevel = ob_get_level();

        ob_start();

        try {
            $__path = $path;
            $__data = $data;

            (function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                require $__path;
            })->call($component);
        } catch (Throwable $e) {
            $this->handleViewException($e, $obLevel);
        }

        return ltrim(ob_get_clean());
    }
}
