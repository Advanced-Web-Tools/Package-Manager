@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading">
        <h1 class="h2" id="pm-installed-title">Installed packages</h1>
        <span class="pm-count text-small text-muted">{{ $totalPackages }} installed</span>
    </div>
    @include('partials/enable.awt.php')
    <section aria-labelledby="pm-installed-title">
        <form class="pm-filters pm-catalog-filters" action="/dashboard/packages" method="get" role="search" aria-label="Search installed packages">
            <div class="pm-search-field">
                <label class="form-label" for="pm-search">Search packages</label>
                <input class="form-control" id="pm-search" type="search" name="q" value="{{ $search }}" maxlength="200" placeholder="Name, description, author or version">
            </div>
            <div class="pm-type-field">
                <label class="form-label" for="pm-type">Package type</label>
                <select class="form-select" id="pm-type" name="type">
                    <option value="">All types</option>
                    @foreach($packageTypes as $key => $label)
                        <option value="{{ $key }}" @if($typeFilter === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Search</button>
            @if($search !== '' || $typeFilter !== '')<a class="btn btn-ghost" href="/dashboard/packages">Clear filters</a>@endif
        </form>
        @if($search !== '' || $typeFilter !== '')
            <p class="pm-results text-small text-muted" role="status">
                Showing {{ count($packages) }} of {{ $totalPackages }} packages
                @if($search !== '') <span>for “{{ $search }}”</span> @endif
                @if($typeFilter !== '') <span>in {{ $packageTypes[$typeFilter] }}</span> @endif
            </p>
        @endif
        @if($totalPackages === 0)
            <div class="card empty-state"><div class="avatar-tile" aria-hidden="true"><i class="fa-solid fa-cubes fa-fw"></i></div><h3 class="h4">No packages installed</h3><p>Browse Marketplace or install a package ZIP from your computer.</p><div class="form-actions"><a class="btn btn-primary" href="/dashboard/packages/browse">Browse Marketplace</a><a class="btn btn-outline-secondary" href="/dashboard/packages/install-from-disk">Install from disk</a></div></div>
        @elseif(count($packages) === 0)
            <div class="card empty-state"><h3 class="h4">No packages match</h3><p>Try another search or select a different package type.</p><a class="btn btn-outline-secondary" href="/dashboard/packages">Clear filters</a></div>
        @else
            <ul class="pm-installed-list">
                @foreach($packages as $item)
                    @include('partials/installed-row.awt.php', ['item' => $item])
                @endforeach
            </ul>
        @endif
    </section>
</div>
    </section>
</div>
@endsection
