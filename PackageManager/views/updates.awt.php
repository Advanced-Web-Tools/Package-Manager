@extends('Dashboard.views.templates.main')
@section('content')
@include('partials/header.awt.php')
<div class="pm-directory">
    <div class="pm-directory-heading"><h1 class="h2">Updates</h1></div>
    @if($updateFailures)
        <section class="card" aria-labelledby="pm-update-failures">
            <h2 class="h4" id="pm-update-failures">Packages that need attention</h2>
            <ul>
                @foreach($updateFailures as $failure)
                    <li>{{ $failure }}</li>
                @endforeach
            </ul>
        </section>
    @endif
    <form class="card-stack" method="post" action="/dashboard/packages/updates/run">
        @include('partials/token.awt.php')
        <section class="card" aria-labelledby="pm-connect-title">
            <h2 class="h4" id="pm-connect-title">Marketplace connections</h2>
            <p class="text-muted">Connect packages whose name identifies a single compatible storefront and whose author matches its release. Connections leave installed versions in place.</p>
            <div class="form-actions">
                <button class="btn btn-outline-primary" name="operation" value="connect-all" type="submit">Connect all packages to Marketplace</button>
            </div>
        </section>
        <section class="card" aria-labelledby="pm-package-update-title">
            <h2 class="h4" id="pm-package-update-title">Package updates</h2>
            <p class="text-muted">Install newer compatible stable releases from connected storefronts.</p>
            <div class="form-actions">
                <button class="btn btn-primary" name="operation" value="update-all" type="submit">Update all packages</button>
            </div>
        </section>
        <section class="card" aria-labelledby="pm-framework-update-title">
            <h2 class="h4" id="pm-framework-update-title">AWT framework</h2>
            <p class="text-muted">Check for the latest AWT release and apply it with the framework updater.</p>
            <div class="form-actions">
                <button class="btn btn-outline-primary" name="operation" value="update-framework" type="submit">Update framework</button>
            </div>
        </section>
    </form>
</div>
    </section>
</div>
@endsection
