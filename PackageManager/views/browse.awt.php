@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading">
        <div>
            <h1 class="h2">Browse packages</h1>
            <p class="text-muted">Discover plugins and themes for your site.</p>
        </div>
        <a class="btn btn-outline-secondary" href="/dashboard/packages"><i class="fa-solid fa-cubes fa-fw" aria-hidden="true"></i> Installed packages</a>
    </div>
    <section class="card empty-state pm-coming-soon" aria-labelledby="browse-title">
        <div class="avatar-tile" aria-hidden="true"><i class="fa-solid fa-compass fa-fw"></i></div>
        <span class="badge badge-muted">Coming soon</span>
        <h2 id="browse-title" class="h3">Package discovery is on its way</h2>
        <p class="text-muted">Browsing packages here isn’t available yet. Already have a package ZIP? Install it directly from your computer.</p>
        <a class="btn btn-primary" href="/dashboard/packages/install-from-disk"><i class="fa-solid fa-upload fa-fw" aria-hidden="true"></i> Install from disk</a>
        <p class="text-small text-muted mb-0">Review your file before installation, then enable the package when you’re ready.</p>
    </section>
</div>
    </section>
</div>
@endsection
