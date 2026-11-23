@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory pm-install-page">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Install from disk</h1>
            <p class="text-muted">Review the archive and its dependencies before installing</p>
        </div>
    </div>
    <ol class="pm-install-steps" aria-label="Installation steps">
        <li @if(!$installation) aria-current="step" @endif><strong>Choose a ZIP</strong></li>
        <li @if($installation) aria-current="step" @endif><strong>Review and install</strong></li>
        <li><strong>Enable in Installed</strong></li>
    </ol>
    @if($installation)
        @include('partials/installation-review.awt.php')
    @else
        @include('partials/upload.awt.php')
    @endif
</div>
    </section>
</div>
@endsection
