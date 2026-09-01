<form id="todo-form" hx-post="{{ $this->action('save') }}">
    <input name="title" value="{{ old('title', $title) }}">
    @error('title')<span class="error">{{ $message }}</span>@enderror
</form>
