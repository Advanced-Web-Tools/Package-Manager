@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory pm-install-page">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Install from disk</h1>
            <p class="text-muted">Add a package to your site in a few steps.</p>
        </div>
        <a class="btn btn-outline-secondary" href="/dashboard/packages"><i class="fa-solid fa-arrow-left fa-fw" aria-hidden="true"></i> Back to packages</a>
    </div>
    <ol class="pm-install-steps" aria-label="Installation steps">
        <li><div><strong>Choose a ZIP</strong><span class="text-small text-muted">Select a package from your computer.</span></div></li>
        <li><div><strong>Review and install</strong><span class="text-small text-muted">Read the manifest, resolve dependencies and confirm installation.</span></div></li>
        <li><div><strong>Enable when ready</strong><span class="text-small text-muted">New packages start disabled. You decide when to enable them.</span></div></li>
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
