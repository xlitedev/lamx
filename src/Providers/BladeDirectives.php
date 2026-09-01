<?php

namespace Xlited\Lamx\Providers;

use Illuminate\Support\Facades\Blade;

class BladeDirectives
{
    public static function register(): void
    {
        /*
         * Wires htmx (v4) to Laravel: sends the CSRF token with every request
         * and shows server errors (5xx) in the modal rendered by @lamxTemplates
         * instead of swapping the error page into the target.
         */
        Blade::directive('lamxScripts', function () {
            return <<<'HTML'
            <script>
                document.addEventListener('htmx:config:request', function (evt) {
                    var token = document.head.querySelector('meta[name="csrf-token"]');
                    if (token) {
                        evt.detail.ctx.request.headers['X-CSRF-TOKEN'] = token.content;
                    }
                });
                document.addEventListener('htmx:before:swap', function (evt) {
                    var ctx = evt.detail.ctx;
                    var modal = document.getElementById('lamxErrorModal');
                    if (!modal || !ctx.response || ctx.response.status < 500) {
                        return;
                    }
                    evt.preventDefault();
                    modal.querySelector('.modal-box').innerHTML = ctx.text;
                    modal.showModal();
                });
            </script>
            HTML;
        });

        Blade::directive('lamxTemplates', function () {
            return <<<'HTML'
            <dialog id="lamxErrorModal" class="modal">
                <div class="modal-box w-11/12 max-w-5xl min-h-[50vh]"></div>
                <form method="dialog" class="modal-backdrop">
                    <button>close</button>
                </form>
            </dialog>
            HTML;
        });
    }
}
