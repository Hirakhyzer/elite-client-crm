<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @if(filter_var(env('PHASE5_NOINDEX', true), FILTER_VALIDATE_BOOL))
        <meta name="robots" content="noindex,nofollow,noarchive">
    @endif
    <title>@yield('title', 'Dare To Dream')</title>
    @hasSection('meta_description')<meta name="description" content="@yield('meta_description')">@endif
    @hasSection('canonical')<link rel="canonical" href="@yield('canonical')">@endif
    <link rel="icon" type="image/png" href="/v11/assets/favicon.png">
    <link rel="stylesheet" href="/phase5b.css">
</head>
<body>
<header class="d2d-header">
    <div class="d2d-nav">
        <a href="/v11/index.html" class="d2d-brand">
            <img src="/v11/assets/d2d-eagle.png" alt="Dare To Dream">
            <span><b>DARE TO DREAM</b><small>DREAM BIG. STUDY ABROAD.</small></span>
        </a>
        <nav>
            <a href="/opportunities?type=scholarship">Scholarships</a>
            <a href="/universities">Universities</a>
            <a href="/v11/index.html#tools">Tools</a>
            <a href="https://portal.dares2dream.com/" rel="noopener">Consultants</a>
            <a href="/blog">Blog</a>
            <a href="/v11/index.html#about">About</a>
        </nav>
        <a class="d2d-cta" href="https://portal.dares2dream.com/">Find Consultant →</a>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="d2d-footer">
    <div><b>Dare To Dream</b> · Discover. Compare. Save. Apply. Track.</div>
</footer>
</body>
</html>
