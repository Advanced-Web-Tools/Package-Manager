@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Your packages</h1>
            <p class="text-muted">Find a package to manage its status, documentation and dependencies.</p>
        </div>
        <a class="btn btn-outline-secondary" href="/dashboard/packages/logs"><i class="fa-solid fa-clock-rotate-left fa-fw" aria-hidden="true"></i> Activity log</a>
    </div>
    @include('partials/enable.awt.php')
    <section aria-labelledby="pm-installed-title">
        <div class="page-heading mb-4">
            <h2 id="pm-installed-title" class="h3">Installed packages</h2>
            <span class="badge badge-muted">{{ $totalPackages }} installed</span>
        </div>
        <form class="pm-filters" action="/dashboard/packages" method="get" role="search" aria-label="Search installed packages">
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
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass fa-fw" aria-hidden="true"></i> Search</button>
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
            <div class="card empty-state"><div class="avatar-tile" aria-hidden="true"><i class="fa-solid fa-cubes fa-fw"></i></div><h3 class="h4">Ready for your first package</h3><p>Use Install from disk to add a trusted package from your computer.</p><a class="btn btn-primary" href="/dashboard/packages/install-from-disk"><i class="fa-solid fa-plus fa-fw" aria-hidden="true"></i> Install your first package</a></div>
        @elseif(count($packages) === 0)
            <div class="card empty-state"><h3 class="h4">No packages match</h3><p>Try another search or select a different package type.</p><a class="btn btn-outline-secondary" href="/dashboard/packages">Clear filters</a></div>
        @else
            <div class="card-grid pm-package-grid">
                @foreach($packages as $item)
                    @include('partials/card.awt.php', ['item' => $item])
                @endforeach
            </div>
        @endif
    </section>
</div>
    </section>
</div>
@endsection
