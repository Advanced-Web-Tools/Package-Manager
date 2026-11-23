<li class="pm-installed-row">
    <div class="pm-installed-identity">
        <img class="pm-market-logo" src="{{ \PackageManager\service\Packages::image($item, 'icon') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="" loading="lazy">
        <div>
            <h2><a href="/dashboard/packages/{{ $item->id }}">{{ $item->name }}</a></h2>
            <span class="text-small text-muted">{{ \PackageManager\service\Packages::typeLabel($item->type) }}@if($item->author) · {{ $item->author }}@endif @if($item->system_package || $item->type === 0) · System @endif</span>
        </div>
    </div>
    @if($item->description)
        <div class="pm-market-card-description text-small">
            @include('partials/description.awt.php', ['descriptionHtml' => (new \PackageManager\service\PackageReadme())->description($item->description, $item, excerpt: true)])
        </div>
    @endif
    <div class="pm-installed-meta text-small">
        <span class="pm-version text-muted">v{{ $item->version }}</span>
        <span class="pm-status {{ $item->status ? 'pm-status-enabled' : 'text-muted' }}">{{ $item->status ? 'Enabled' : 'Disabled' }}</span>
    </div>
    <i class="fa-solid fa-chevron-right pm-installed-arrow" aria-hidden="true"></i>
</li>
