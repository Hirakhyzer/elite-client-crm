@extends('phase5b.layout')
@section('title','Opportunities — Dare To Dream')
@section('content')
<section class="d2d-hero"><div class="inner"><div class="d2d-kicker">Scholarships · Jobs · Internships</div><h1>FIND THE<br>RIGHT OPENING.</h1><p>Published opportunities now come directly from the D2D CRM/shared database.</p></div></section>
<section class="d2d-section">
<form class="d2d-toolbar" method="get"><input class="d2d-input" name="q" value="{{ $q }}" placeholder="Search opportunities"><select class="d2d-select" name="type"><option value="">All types</option>@foreach(['scholarship','job','internship','fellowship'] as $option)<option value="{{ $option }}" @selected($type===$option)>{{ ucfirst($option) }}</option>@endforeach</select><button class="d2d-button">Filter</button></form>
@if($items->isEmpty())<div class="d2d-empty">No published opportunities matched this view.</div>@else
<div class="d2d-grid">@foreach($items as $item)<article class="d2d-card">@if($item['image'])<div class="d2d-card-media"><img src="{{ $item['image'] }}" alt=""></div>@endif<div class="d2d-card-body"><span class="d2d-chip">{{ $item['type'] ?: 'Opportunity' }}</span><h2>{{ $item['title'] }}</h2>@if($item['country'] || $item['location'])<div class="d2d-meta">{{ $item['location'] }}{{ $item['location'] && $item['country'] ? ', ' : '' }}{{ $item['country'] }}</div>@endif@if($item['deadline'])<div class="d2d-meta">Deadline: {{ $item['deadline'] }}</div>@endif@if($item['summary'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['summary']),150) }}</p>@endif<a class="d2d-link" href="/opportunities/{{ $item['slug'] }}">View opportunity →</a></div></article>@endforeach</div>
@endif
</section>
@endsection
