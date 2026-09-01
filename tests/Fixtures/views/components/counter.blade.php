<div id="counter">
    <span class="label">{{ $this->label }}</span>
    <span class="count">{{ $this->count }}</span>
    <button hx-post="{{ $this->action('increment') }}">+</button>
    <button hx-post="{{ $this->action('add', ['amount' => 5]) }}">+5</button>
    {{ $slot }}
</div>
