<article class="card pm-market-card">
    <div class="pm-market-card-cover">
        <img class="pm-market-media" src="{{ \PackageManager\service\Packages::image($item, 'preview') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="" loading="lazy">
    </div>
    <div class="pm-market-card-body">
        <header class="pm-market-card-title">
            <img class="pm-market-logo" src="{{ \PackageManager\service\Packages::image($item, 'icon') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="" loading="lazy">
            <div>
                <h2><a href="/dashboard/packages/{{ $item->id }}">{{ $item->name }}</a></h2>
                <span class="text-small text-muted">{{ \PackageManager\service\Packages::typeLabel($item->type) }}@if($item->author) · {{ $item->author }} @endif</span>
            </div>
        </header>
        @if($item->description)
            <div class="pm-market-card-description">
                @include('partials/description.awt.php', ['descriptionHtml' => (new \PackageManager\service\PackageReadme())->description($item->description, $item, excerpt: true)])
            </div>
        @endif
        <footer class="pm-market-card-meta text-small text-muted">
            <span>v{{ $item->version }}@if($item->system_package || $item->type === 0) · System @endif</span>
            <span class="pm-status {{ $item->status ? 'pm-status-enabled' : '' }}">{{ $item->status ? 'Enabled' : 'Disabled' }}</span>
        </footer>
    </div>
</article>
