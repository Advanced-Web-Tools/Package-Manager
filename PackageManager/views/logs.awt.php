@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2" id="pm-log-title">Package activity</h1>
            <p class="text-muted">Package changes and their outcomes, newest first · UTC</p>
        </div>
    </div>
    <section class="card pm-log-card" aria-labelledby="pm-log-title">
        @if(count($entries) === 0)
            <div class="empty-state">
                <h2 class="h4">{{ $logPage > 1 ? 'No activity on this page' : 'No package activity yet' }}</h2>
                <p class="text-muted">{{ $logPage > 1 ? 'Go to an earlier page to see recorded actions.' : 'Installations, version changes and package actions appear here.' }}</p>
            </div>
        @else
            <table class="table pm-log-table" role="table" aria-labelledby="pm-log-title">
                <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Package and action</th><th scope="col" role="columnheader">Outcome</th><th scope="col" role="columnheader">Time and user</th><th scope="col" role="columnheader">Details</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach($entries as $entry)
                        <tr role="row">
                            <td role="cell">
                                <strong class="pm-log-package">{{ $entry['package_name'] }}</strong>
                                <span class="pm-log-meta text-small text-muted">{{ ucfirst($entry['action']) }}</span>
                            </td>
                            <td role="cell"><span class="pm-status pm-outcome-{{ in_array($entry['outcome'], ['success', 'failed', 'pending'], true) ? $entry['outcome'] : 'pending' }}">{{ ucfirst($entry['outcome']) }}</span></td>
                            <td role="cell">
                                <time datetime="{{ str_replace(' ', 'T', $entry['created_at']) . 'Z' }}">{{ $entry['created_at'] }}</time>
                                <span class="pm-log-meta text-small text-muted">{{ $entry['account_name'] }}</span>
                            </td>
                            <td role="cell">{{ $entry['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="pm-log-note text-small text-muted">Pending means the action started and its final result hasn’t been recorded.</p>
        @endif
        @if($hasMore || $logPage > 1)
            <nav class="pm-log-pagination" aria-label="Log pages">
                <span class="text-small text-muted" aria-current="page">Page {{ $logPage }}</span>
                <div class="pm-log-links">
                    @if($logPage > 1)<a class="btn btn-outline-secondary" href="/dashboard/packages/logs?page={{ $logPage - 1 }}">Newer actions</a>@endif
                    @if($hasMore)<a class="btn btn-outline-secondary" href="/dashboard/packages/logs?page={{ $logPage + 1 }}">Older actions</a>@endif
                </div>
            </nav>
        @endif
    </section>
</div>
    </section>
</div>
@endsection
