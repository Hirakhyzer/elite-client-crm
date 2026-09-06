@extends('phase5b.layout')
@section('title','Universities — Dare To Dream')
@section('content')
<section class="d2d-hero"><div class="inner"><div class="d2d-kicker">Compare destinations</div><h1>FIND YOUR<br>UNIVERSITY.</h1><p>University content is read from the shared D2D database. If the CRM stores universities inside content_posts, Phase 5B detects that automatically.</p></div></section>
<section class="d2d-section">
<form class="d2d-toolbar" method="get"><input class="d2d-input" name="q" value="{{ $q }}" placeholder="Search universities"><button class="d2d-button">Search</button></form>
@if($items->isEmpty())<div class="d2d-empty">No published university records were detected yet. Run <b>php artisan phase5b:doctor</b> to see which CRM source is available.</div>@else
<div class="d2d-grid">@foreach($items as $item)<article class="d2d-card">@if($item['image'])<div class="d2d-card-media"><img src="{{ $item['image'] }}" alt=""></div>@endif<div class="d2d-card-body"><span class="d2d-chip">University</span><h2>{{ $item['title'] }}</h2>@if($item['country'] || $item['location'])<div class="d2d-meta">{{ $item['location'] }}{{ $item['location'] && $item['country'] ? ', ' : '' }}{{ $item['country'] }}</div>@endif@if($item['summary'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['summary']),150) }}</p>@endif<a class="d2d-link" href="/universities/{{ $item['slug'] }}">View university →</a></div></article>@endforeach</div>
@endif
</section>
@endsection
