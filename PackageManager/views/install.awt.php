@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory pm-install-page">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Install from disk</h1>
            <p class="text-muted">Upload an AWT package ZIP and review its dependencies</p>
        </div>
    </div>
    <ol class="pm-install-steps" aria-label="Installation steps">
        <li><div><strong>Choose a ZIP</strong><span class="text-small text-muted">Upload from your computer.</span></div></li>
        <li><div><strong>Review and install</strong><span class="text-small text-muted">Check the manifest and resolve any missing dependencies.</span></div></li>
        <li><div><strong>Enable when ready</strong><span class="text-small text-muted">Enable the package from Installed when you’re ready.</span></div></li>
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
