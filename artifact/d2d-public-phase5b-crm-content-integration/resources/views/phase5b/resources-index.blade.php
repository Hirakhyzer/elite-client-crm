@extends('phase5b.layout')
@section('title','Resources — Dare To Dream')
@section('content')
<section class="d2d-hero"><div class="inner"><div class="d2d-kicker">Guidebooks & resources</div><h1>USEFUL TOOLS.<br>REAL NEXT STEPS.</h1><p>Published public resources are connected directly to the CRM guidebook library.</p></div></section>
<section class="d2d-section">
<form class="d2d-toolbar" method="get"><input class="d2d-input" name="q" value="{{ $q }}" placeholder="Search resources"><button class="d2d-button">Search</button></form>
@if($items->isEmpty())<div class="d2d-empty">No public guidebooks/resources are published yet.</div>@else
<div class="d2d-grid">@foreach($items as $item)<article class="d2d-card">@if($item['image'])<div class="d2d-card-media"><img src="{{ $item['image'] }}" alt=""></div>@endif<div class="d2d-card-body"><span class="d2d-chip">{{ $item['type'] ?: 'Resource' }}</span><h2>{{ $item['title'] }}</h2>@if($item['summary'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['summary']),160) }}</p>@endif<div class="d2d-meta">{{ $item['author'] }}</div></div></article>@endforeach</div>
@endif
</section>
@endsection
