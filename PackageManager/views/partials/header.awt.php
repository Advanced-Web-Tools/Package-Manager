<link rel="stylesheet" href="/awt_packages/Dashboard/views/assets/css/main.css">
<link rel="stylesheet" href="/awt_packages/PackageManager/views/assets/css/main.css">
<script src="/awt_packages/PackageManager/views/assets/js/packages.js" defer></script>
<div class="pm-page">
    <header class="navbar border-bottom">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                <div class="navbar-brand">Packages</div>
                <a class="btn btn-ghost" href="/dashboard/packages/help"><i class="fa-solid fa-circle-question fa-fw" aria-hidden="true"></i> Help</a>
            </div>
            @if($package)
                <div class="theme-hero">
                    <div class="theme-hero-content">
                        <a class="theme-back" href="/dashboard/packages"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Back to packages</a>
                        <div class="theme-heading">
                            <div class="avatar-tile theme-icon-large" aria-hidden="true">
                                <img src="{{ \PackageManager\service\Packages::image($package, 'icon') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="">
                            </div>
                            <div>
                                <h1>{{ $package->name }}</h1>
                                <span class="badge {{ $package->status ? '' : 'badge-muted' }}">{{ $package->status ? 'Enabled' : 'Disabled' }}</span>
                                @if($package->system_package || $package->type === 0)<span class="badge badge-muted">System package</span>@endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            @if($package)
            <nav class="nav-tabs" aria-label="Package details">
                <a href="/dashboard/packages/{{ $package->id }}" class="{{ $packageTab === 'overview' ? 'active' : '' }}" @if($packageTab === 'overview') aria-current="page" @endif><i class="fa-solid fa-circle-info fa-fw" aria-hidden="true"></i> Overview</a>
                @if($readme)
                    <a href="/dashboard/packages/{{ $package->id }}?tab=readme" class="{{ $packageTab === 'readme' ? 'active' : '' }}" @if($packageTab === 'readme') aria-current="page" @endif><i class="fa-solid fa-book-open fa-fw" aria-hidden="true"></i> README</a>
                @endif
                @if($package->license || \PackageManager\service\Packages::licenseUrl($package->license_url))
                    <a href="/dashboard/packages/{{ $package->id }}?tab=license" class="{{ $packageTab === 'license' ? 'active' : '' }}" @if($packageTab === 'license') aria-current="page" @endif><i class="fa-solid fa-scale-balanced fa-fw" aria-hidden="true"></i> License</a>
                @endif
            </nav>
            @else
            <nav class="nav-tabs" aria-label="Package pages">
                <a href="/dashboard/packages" class="{{ $tab === 'Installed' ? 'active' : '' }}" @if($tab === 'Installed') aria-current="page" @endif><i class="fa-solid fa-cubes fa-fw" aria-hidden="true"></i> Installed</a>
                <a href="/dashboard/packages/browse" class="{{ $tab === 'Browse' ? 'active' : '' }}" @if($tab === 'Browse') aria-current="page" @endif><i class="fa-solid fa-compass fa-fw" aria-hidden="true"></i> Browse</a>
                <a href="/dashboard/packages/install-from-disk" class="{{ $tab === 'Install from disk' ? 'active' : '' }}" @if($tab === 'Install from disk') aria-current="page" @endif><i class="fa-solid fa-upload fa-fw" aria-hidden="true"></i> Install from disk</a>
            </nav>
            @endif
        </div>
    </header>
    <section class="container py-5" aria-label="Package management">
        @if($notice)
            <div class="alert {{ $notice['kind'] === 'error' ? 'alert-danger' : 'alert-success' }}" role="{{ $notice['kind'] === 'error' ? 'alert' : 'status' }}">{{ $notice['message'] }}</div>
        @endif
