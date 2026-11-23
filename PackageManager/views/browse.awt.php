@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory pm-market">
    @if(!$marketplaceBrowse['detail'] && empty($marketplaceBrowse['profile']))
        <div class="pm-directory-heading pm-market-heading">
            <div>
                <h1 class="h2">Browse Marketplace</h1>
                <p class="text-muted">Find AWT plugins and themes to install on this server</p>
            </div>
        </div>
    @endif

    @if($marketplaceBrowse['error'])
        <div class="alert alert-danger" role="alert">{{ $marketplaceBrowse['error'] }}</div>
    @endif

    @if($marketplaceBrowse['detail'])
        <a class="pm-back-to-browse" href="/dashboard/packages/browse"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Marketplace</a>
        @include('partials/marketplace-detail.awt.php', ['item' => $marketplaceBrowse['detail']])
    @elseif(!empty($marketplaceBrowse['profile']))
        <a class="pm-back-to-browse" href="/dashboard/packages/browse"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Marketplace</a>
        @include('partials/marketplace-profile.awt.php', ['profile' => $marketplaceBrowse['profile']])
    @else
        <form class="pm-filters pm-market-filters" action="/dashboard/packages/browse" method="get" role="search" aria-label="Search Marketplace">
            <div class="pm-search-field">
                <label class="form-label" for="pm-market-search">Package name or description</label>
                <input class="form-control" id="pm-market-search" type="search" name="q" value="{{ $marketplaceBrowse['search'] }}" maxlength="200">
            </div>
            <div class="pm-type-field">
                <label class="form-label" for="pm-market-tag">Tag</label>
                <select class="form-select" id="pm-market-tag" name="tag">
                    <option value="">All tags</option>
                    @foreach($marketplaceBrowse['tags'] as $tag)
                        <option value="{{ $tag['id'] }}" @if($marketplaceBrowse['tag'] === $tag['id']) selected @endif>{{ $tag['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pm-type-field">
                <label class="form-label" for="pm-market-sort">Sort by</label>
                <select class="form-select" id="pm-market-sort" name="sort">
                    @foreach($marketplaceBrowse['sorts'] as $key => $label)
                        <option value="{{ $key }}" @if($marketplaceBrowse['sort'] === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pm-market-filter-actions">
                <button class="btn btn-primary" type="submit">Search</button>
                @if($marketplaceBrowse['section'] || array_key_exists('search', $marketplaceBrowse['sections']) || $marketplaceBrowse['search'] !== '' || $marketplaceBrowse['tag'] !== '' || $marketplaceBrowse['sort'] !== 'name')
                    <a class="btn btn-ghost" href="/dashboard/packages/browse">Reset</a>
                @endif
            </div>
        </form>

        @if(!$marketplaceBrowse['sections'] && !$marketplaceBrowse['error'])
            <div class="card empty-state"><h2 class="h4">No packages are available</h2></div>
        @endif
        @foreach($marketplaceBrowse['sections'] as $key => $items)
            <section aria-labelledby="pm-market-{{ $key }}">
                <div class="pm-market-section-heading">
                    <h2 id="pm-market-{{ $key }}" class="h3">{{ $marketplaceBrowse['labels'][$key] ?? 'Search results' }}</h2>
                    @if($key !== 'search' && !$marketplaceBrowse['section'])
                        <a class="pm-market-section-link" href="/dashboard/packages/browse?section={{ $key }}">View all<span class="visually-hidden"> {{ strtolower($marketplaceBrowse['labels'][$key]) }} packages</span></a>
                    @endif
                </div>
                @if($items)
                    <div class="card-grid pm-market-grid">
                        @foreach($items as $item)
                            @include('partials/marketplace-card.awt.php', ['item' => $item])
                        @endforeach
                    </div>
                @else
                    <div class="card empty-state"><h3 class="h4">No packages on this page</h3><p>Try a different search or clear the filters.</p></div>
                @endif
            </section>
        @endforeach
    @endif
    @if($marketplaceBrowse['previous'] || $marketplaceBrowse['next'])
        <nav class="pm-log-pagination" aria-label="Marketplace pages">
            @if($marketplaceBrowse['previous']) <a class="btn btn-outline-secondary" href="{{ $marketplaceBrowse['previous'] }}">Previous</a> @endif
            <span class="text-small text-muted">Page {{ $marketplaceBrowse['page'] }}</span>
            @if($marketplaceBrowse['next']) <a class="btn btn-outline-secondary" href="{{ $marketplaceBrowse['next'] }}">Next</a> @endif
        </nav>
    @endif
</div>
    </section>
</div>
@endsection
