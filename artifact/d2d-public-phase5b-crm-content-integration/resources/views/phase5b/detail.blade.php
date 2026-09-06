@php($seo = $item['seo'] ?? [])
@extends('phase5b.layout')
@section('title', ($seo['title'] ?? $item['title']) . ' — Dare To Dream')
@if(!empty($seo['description']))@section('meta_description', $seo['description'])@endif
@if(!empty($seo['canonical']))@section('canonical', $seo['canonical'])@endif
@section('content')
<article class="d2d-detail">
    <div class="d2d-kicker">{{ ucfirst($item['kind'] ?? 'Content') }}@if($item['type']) · {{ $item['type'] }}@endif</div>
    <h1>{{ $item['title'] }}</h1>
    @if($item['summary'])<p class="lede">{{ strip_tags($item['summary']) }}</p>@endif
    @if($item['image'])<img class="cover" src="{{ $item['image'] }}" alt="{{ $item['title'] }}">@endif
    @if($item['country'] || $item['location'] || $item['deadline'])<p class="d2d-meta">@if($item['location']){{ $item['location'] }}@endif @if($item['country']){{ $item['country'] }}@endif @if($item['deadline']) · Deadline: {{ $item['deadline'] }}@endif</p>@endif
    @if($item['apply_url'])<a class="d2d-apply" href="{{ $item['apply_url'] }}" rel="noopener" target="_blank">Apply / Learn more →</a>@endif
    @if($item['content_html'])<div class="d2d-prose">{!! $item['content_html'] !!}</div>@endif
</article>
@endsection
