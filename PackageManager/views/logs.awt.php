@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Package activity</h1>
            <p class="text-muted">Review package changes, who made them and whether they succeeded.</p>
        </div>
        <a class="btn btn-outline-secondary" href="/dashboard/packages"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Back to packages</a>
    </div>
    <section class="card pm-log-card" aria-labelledby="pm-log-title">
        <div class="page-heading">
            <h2 id="pm-log-title" class="h3">Activity log</h2>
            @if(count($entries) > 0)<span class="badge badge-muted">{{ count($entries) }} on this page</span>@endif
        </div>
        @if(count($entries) === 0)
            <div class="empty-state">
                <div class="avatar-tile" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left fa-fw"></i></div>
                <h3 class="h4">{{ $logPage > 1 ? 'No activity on this page' : 'No package activity yet' }}</h3>
                <p class="text-muted">{{ $logPage > 1 ? 'Return to an earlier page to view recorded actions.' : 'Install, enable, disable and remove actions will appear here.' }}</p>
            </div>
        @else
            <div class="pm-table-wrap">
                <table class="table pm-log-table">
                    <caption class="text-small text-muted">Newest actions first. All times are in UTC.</caption>
                    <thead><tr><th scope="col">When / who</th><th scope="col">Package / action</th><th scope="col">Outcome</th><th scope="col">Details</th></tr></thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td>
                                    <time datetime="{{ str_replace(' ', 'T', $entry['created_at']) . 'Z' }}">{{ $entry['created_at'] }}</time>
                                    <span class="pm-log-meta text-small text-muted"><i class="fa-solid fa-user fa-fw" aria-hidden="true"></i> {{ $entry['account_name'] }}</span>
                                </td>
                                <td>
                                    <strong class="pm-log-package">{{ $entry['package_name'] }}</strong>
                                    <span class="pm-log-meta text-small text-muted">{{ ucfirst($entry['action']) }}</span>
                                </td>
                                <td><span class="badge pm-outcome-{{ in_array($entry['outcome'], ['success', 'failed', 'pending'], true) ? $entry['outcome'] : 'pending' }}"><i class="fa-solid {{ $entry['outcome'] === 'success' ? 'fa-circle-check' : ($entry['outcome'] === 'failed' ? 'fa-circle-xmark' : 'fa-clock') }} fa-fw" aria-hidden="true"></i> {{ ucfirst($entry['outcome']) }}</span></td>
                                <td>{{ $entry['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="pm-log-note text-small text-muted"><i class="fa-solid fa-circle-info fa-fw" aria-hidden="true"></i> Pending means an action started but no final result was recorded.</p>
        @endif
        @if(count($entries) > 0 || $logPage > 1)
            <nav class="pm-log-pagination" aria-label="Log pages">
                @if($logPage > 1)
                    <a class="btn btn-outline-secondary" href="/dashboard/packages/logs?page={{ $logPage - 1 }}"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Newer actions</a>
                @else
                    <button class="btn btn-outline-secondary" type="button" disabled><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Newer actions</button>
                @endif
                <span class="text-small text-muted" aria-current="page">Page {{ $logPage }}</span>
                @if($hasMore)
                    <a class="btn btn-outline-secondary" href="/dashboard/packages/logs?page={{ $logPage + 1 }}">Older actions <i class="fa-solid fa-arrow-right fa-fw" aria-hidden="true"></i></a>
                @else
                    <button class="btn btn-outline-secondary" type="button" disabled>Older actions <i class="fa-solid fa-arrow-right fa-fw" aria-hidden="true"></i></button>
                @endif
            </nav>
        @endif
    </section>
</div>
    </section>
</div>
@endsection
