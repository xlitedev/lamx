<div id="post-{{ $this->post->id }}">
    <h1>{{ $this->post->title }}</h1>
    <ul>@foreach ($this->related as $item)<li>{{ $item->title }}</li>@endforeach</ul>
</div>
