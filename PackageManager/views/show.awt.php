@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-detail-layout">
<div class="card-stack pm-detail-stack">
    @if($packageTab === 'overview')
    <section class="card pm-overview" aria-labelledby="metadata-title">
        <img class="pm-package-preview" src="{{ \PackageManager\service\Packages::image($package, 'preview') }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="{{ $package->name }} preview">
        <div class="page-heading">
            <h2 id="metadata-title" class="h3">About this package</h2>
            <span class="text-small text-muted">Version {{ $package->version }}</span>
        </div>
        <dl class="theme-package-details">
            <div><dt>Author</dt><dd>{{ $package->author ?: 'Not specified' }}</dd></div>
            <div><dt>Package type</dt><dd>{{ \PackageManager\service\Packages::typeLabel($package->type) }}</dd></div>
            <div><dt>AWT compatibility</dt><dd>{{ $package->minimum_awt_version }}{{ $package->maximum_awt_version ? ' – ' . $package->maximum_awt_version : ' or later' }}</dd></div>
            <div><dt>License</dt><dd>{{ $package->license ?: 'Not specified' }}</dd></div>
        </dl>
        <details class="pm-manifest">
            <summary>View all metadata <span class="text-small text-muted">Installed database record</span></summary>
            <dl class="theme-package-details">
                <div><dt>Installed ID</dt><dd>{{ $package->id }}</dd></div>
                <div><dt>Status</dt><dd>{{ $package->status ? 'Enabled' : 'Disabled' }}</dd></div>
                @foreach($metadata as $key => $value)
                    @if($key !== 'dependencies')
                        <div><dt>{{ ucwords(str_replace('_', ' ', $key)) }}</dt><dd>
                            @if(is_array($value) || is_object($value))
                                <pre>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @elseif(is_bool($value))
                                {{ $value ? 'true' : 'false' }}
                            @elseif($value === null)
                                <span class="text-muted">Not specified</span>
                            @else
                                {{ $value }}
                            @endif
                        </dd></div>
                    @endif
                @endforeach
            </dl>
            <p class="text-small text-muted">Dependency requirements and installed status are shown in the Dependencies card below.</p>
        </details>
        <section class="pm-description-section" aria-labelledby="pm-description-title">
            <h3 id="pm-description-title" class="h5">Description</h3>
            <p class="theme-package-description">{{ $description ?: 'No description provided.' }}</p>
        </section>
        @if($readmeError)
            <p class="alert alert-warning" role="status">{{ $readmeError }}</p>
        @endif
    </section>
    <section class="card" aria-labelledby="pm-dependencies-title">
        <div class="page-heading"><h2 id="pm-dependencies-title" class="h3">Dependencies</h2></div>
        <p class="text-muted">Required packages and their current status on this site.</p>
        @include('partials/dependencies.awt.php')
    </section>
    @elseif($packageTab === 'readme' && $readme)
    <section class="card" aria-labelledby="pm-readme-title">
        <div class="page-heading">
            <h2 id="pm-readme-title" class="h3">README</h2>
            <span class="text-small text-muted">{{ $readme['filename'] }}</span>
        </div>
        <div class="pm-readme">{!! $readme['html'] !!}</div>
    </section>
    @elseif($packageTab === 'license')
    <section class="card pm-license" aria-labelledby="pm-license-title">
        <div class="page-heading"><h2 id="pm-license-title" class="h3">{{ $package->license ?: 'Package license' }}</h2></div>
        @if(\PackageManager\service\Packages::licenseUrl($package->license_url))
            <p class="text-small text-muted">If the publisher prevents embedding, open the license in a new tab.</p>
            <a class="btn btn-outline-primary mb-3" href="{{ \PackageManager\service\Packages::licenseUrl($package->license_url) }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square fa-fw" aria-hidden="true"></i> Open original license</a>
            <iframe src="{{ \PackageManager\service\Packages::licenseUrl($package->license_url) }}" title="Package license document" sandbox referrerpolicy="no-referrer"></iframe>
        @else
            <p class="text-muted">This package is licensed under {{ $package->license }}.</p>
        @endif
    </section>
    @endif
</div>
<aside class="pm-package-actions" aria-labelledby="pm-actions-title">
    <section class="card" aria-labelledby="pm-actions-title">
        <div class="page-heading"><h2 id="pm-actions-title" class="h3">Package actions</h2></div>
        @if($package->system_package || $package->type === 0)
            <p class="text-muted">System packages are protected. They cannot be disabled or removed.</p>
        @else
            <p class="text-muted">This package is currently {{ $package->status ? 'enabled' : 'disabled' }}. Changes take effect on the next request. Removing a package keeps its stored data and database tables.</p>
        @endif
        <div class="form-actions">
            @foreach(['enable' => 'Enable', 'disable' => 'Disable'] as $operation => $label)
                <form method="post" action="/dashboard/packages/action">
                    @include('partials/token.awt.php')
                    <input type="hidden" name="package_id" value="{{ $package->id }}">
                    <input type="hidden" name="operation" value="{{ $operation }}">
                    <input type="hidden" name="return_tab" value="{{ $packageTab }}">
                    <button class="btn {{ $operation === 'enable' ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit"
                        @if(($operation === 'enable' && $package->status) || ($operation === 'disable' && (!$package->status || $package->system_package || $package->type === 0))) disabled @endif>
                        <i class="fa-solid fa-power-off fa-fw" aria-hidden="true"></i> {{ $label }}
                    </button>
                </form>
            @endforeach
            @if(!$package->system_package && $package->type !== 0)
                <button class="btn btn-danger" type="button" data-open-dialog="pm-remove-dialog"><i class="fa-solid fa-trash fa-fw" aria-hidden="true"></i> Remove</button>
            @else
                <button class="btn btn-danger" type="button" disabled><i class="fa-solid fa-trash fa-fw" aria-hidden="true"></i> Remove</button>
            @endif
        </div>
    </section>
</aside>
</div>
@if(!$package->system_package && $package->type !== 0)
<dialog id="pm-remove-dialog" class="pm-dialog" aria-labelledby="pm-remove-title" aria-describedby="pm-remove-description">
    <h2 id="pm-remove-title" class="h3">Remove {{ $package->name }}?</h2>
    <p id="pm-remove-description">This removes the package code and resource records. Owned storage and tables are retained. Packages that rely on it must be removed first.</p>
    <form action="/dashboard/packages/action" method="post">
        @include('partials/token.awt.php')
        <input type="hidden" name="package_id" value="{{ $package->id }}">
        <input type="hidden" name="operation" value="remove">
        <input type="hidden" name="confirmed" value="1">
        <div class="form-actions">
            <button class="btn btn-outline-secondary" type="button" data-close-dialog autofocus>Cancel</button>
            <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash fa-fw" aria-hidden="true"></i> Remove package</button>
        </div>
    </form>
</dialog>
@endif
    </section>
</div>
@endsection
