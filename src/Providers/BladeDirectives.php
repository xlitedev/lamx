<?php

namespace Xlited\Lamx\Providers;

use Illuminate\Support\Facades\Blade;

class BladeDirectives
{
    public static function register(): void
    {
        /*
         * Wires htmx (v4) to Laravel:
         *  - sends the CSRF token with every request;
         *  - when Alpine.js is loaded, sends the Alpine scope of the component
         *    the request comes from as "_lamx_data", so #[Bindable] properties
         *    edited in the browser reach the server;
         *  - shows server errors (5xx) in the modal rendered by @lamxTemplates
         *    instead of swapping the error page into the target.
         */
        Blade::directive('lamxScripts', function () {
            return <<<'HTML'
            <script>
                document.addEventListener('htmx:config:request', function (evt) {
                    var ctx = evt.detail.ctx;
                    var token = document.head.querySelector('meta[name="csrf-token"]');
                    if (token) {
                        ctx.request.headers['X-CSRF-TOKEN'] = token.content;
                    }
                    if (!window.Alpine || !ctx.sourceElement || !ctx.request.body || typeof ctx.request.body.set !== 'function') {
                        return;
                    }
                    var root = ctx.sourceElement.closest('[x-data][hx-vals\\:inherited]');
                    if (root) {
                        ctx.request.body.set('_lamx_data', JSON.stringify(Alpine.$data(root)));
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
