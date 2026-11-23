<article class="card pm-market-card">
    <div class="pm-market-card-cover">
        <img class="pm-market-media" src="{{ $item['cover'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="" loading="lazy">
        <video class="pm-market-media pm-market-card-video" muted loop playsinline preload="none" aria-hidden="true"
            data-hover-preview="{{ $item['id'] }}" data-preview-url="{{ $item['video'] ?? '' }}"></video>
    </div>
    <div class="pm-market-card-body">
        <header class="pm-market-card-title">
            <img class="pm-market-logo" src="{{ $item['logo'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="" loading="lazy">
            <h3><a href="/dashboard/packages/browse?package={{ $item['id'] }}">{{ $item['name'] }}</a></h3>
        </header>
        @if($item['description'])
            <div class="pm-market-card-description">
                @include('partials/description.awt.php', ['descriptionHtml' => $item['description_excerpt'] ?? (new \PackageManager\service\PackageReadme())->description($item['description'], excerpt: true)])
            </div>
        @endif
        <footer class="pm-market-card-meta text-small text-muted">
            <span>{{ $item['release'] ? 'v' . $item['release']['version'] : 'No compatible stable release' }}@if($item['installed_id']) · Installed @endif</span>
            @if($item['rating']['count'])
                <span><i class="fa-solid fa-star" aria-hidden="true"></i> <span class="visually-hidden">Rating</span> {{ number_format($item['rating']['score'], 1) }} <span aria-label="{{ $item['rating']['count'] }} reviews">({{ $item['rating']['count'] }})</span></span>
            @endif
        </footer>
    </div>
</article>
