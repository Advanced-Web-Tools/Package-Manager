<div class="card theme-card">
    <a class="theme-card-cover" href="/dashboard/packages/{{ $item->id }}" aria-label="View {{ $item->name }}">
        <img src="{{ \PackageManager\service\Packages::image($item, 'preview') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="" loading="lazy">
    </a>
    <div class="card-header">
        <a class="avatar-tile" href="/dashboard/packages/{{ $item->id }}" aria-label="View {{ $item->name }}">
            <img src="{{ \PackageManager\service\Packages::image($item, 'icon') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="" loading="lazy">
        </a>
        <span class="badge {{ $item->status ? '' : 'badge-muted' }}">{{ $item->system_package || $item->type === 0 ? 'System · ' : '' }}{{ $item->status ? 'Enabled' : 'Disabled' }}</span>
    </div>
    <h2 class="card-title"><a class="theme-card-link" href="/dashboard/packages/{{ $item->id }}">{{ $item->name }}</a></h2>
    <p class="pm-card-meta text-small text-muted">{{ \PackageManager\service\Packages::typeLabel($item->type) }}@if($item->author) · {{ $item->author }}@endif</p>
    <p class="card-description">{{ $item->description ?: 'No description provided.' }}</p>
    <div class="card-footer">
        <span class="text-small text-muted">Version {{ $item->version }}</span>
        <a class="btn btn-outline-primary" href="/dashboard/packages/{{ $item->id }}"><i class="fa-solid fa-circle-info fa-fw" aria-hidden="true"></i> Manage package</a>
    </div>
</div>
