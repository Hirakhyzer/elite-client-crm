@extends('phase5b.layout')
@section('title','Blog — Dare To Dream')
@section('content')
<section class="d2d-hero"><div class="inner"><div class="d2d-kicker">D2D Editorial</div><h1>STUDY ABROAD<br>INSIGHTS.</h1><p>Real articles published from the D2D CRM, connected to the shared database.</p></div></section>
<section class="d2d-section">
<form class="d2d-toolbar" method="get"><input class="d2d-input" name="q" value="{{ $q }}" placeholder="Search articles"><button class="d2d-button">Search</button></form>
@if($items->isEmpty())<div class="d2d-empty">No published blog posts matched this view.</div>@else
<div class="d2d-grid">@foreach($items as $item)<article class="d2d-card">@if($item['image'])<div class="d2d-card-media"><img src="{{ $item['image'] }}" alt=""></div>@endif<div class="d2d-card-body"><span class="d2d-chip">Blog</span><h2>{{ $item['title'] }}</h2>@if($item['summary'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['summary']),150) }}</p>@endif<div class="d2d-meta">{{ $item['author'] }}@if($item['published_at']) · {{ $item['published_at'] }}@endif</div><a class="d2d-link" href="/blog/{{ $item['slug'] }}">Read article →</a></div></article>@endforeach</div>
@endif
</section>
@endsection
