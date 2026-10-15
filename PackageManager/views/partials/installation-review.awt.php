<section class="card" aria-labelledby="pm-review-title">
    <div class="page-heading">
        <h2 id="pm-review-title" class="h3">Review {{ $installation['manifest']['name'] }}</h2>
        <span class="badge badge-muted">Version {{ $installation['manifest']['version'] }}</span>
    </div>
    <p class="text-muted">{{ $installation['manifest']['description'] ?? 'No description provided.' }}</p>
    <dl class="theme-package-details">
        <div><dt>Author</dt><dd>{{ $installation['manifest']['author'] ?? 'Not specified' }}</dd></div>
        <div><dt>AWT compatibility</dt><dd>{{ $installation['manifest']['minimum_awt_version'] }}{{ !empty($installation['manifest']['maximum_awt_version']) ? ' – ' . $installation['manifest']['maximum_awt_version'] : ' or later' }}</dd></div>
        <div><dt>Packages prepared</dt><dd>{{ count($installation['nodes']) }}</dd></div>
    </dl>
    <h3 class="h4">Dependency check</h3>
    @if(!$installation['dependencies'])
        <p class="alert alert-success">This package has no dependencies.</p>
    @else
        <p class="text-muted">Requirements are checked recursively. Dependencies are installed before the packages that need them.</p>
        <ul class="pm-review-dependencies">
            @foreach($installation['dependencies'] as $dependency)
                <li>
                    <div class="page-heading">
                        <strong>{{ $dependency['name'] }} <code>{{ $dependency['version'] }}</code></strong>
                        <span class="badge {{ $dependency['status'] === 'installed' || $dependency['status'] === 'prepared' ? 'pm-outcome-success' : 'pm-outcome-failed' }}">{{ ucfirst($dependency['status']) }}</span>
                    </div>
                    <p class="text-small text-muted">Required by {{ $dependency['parent'] }}@if($dependency['installed_version']) · Installed version {{ $dependency['installed_version'] }}@endif</p>
                    @if($dependency['url'])
                        <a class="pm-dependency-source text-small" href="{{ $dependency['url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square fa-fw" aria-hidden="true"></i> Source: {{ $dependency['url'] }}</a>
                    @elseif($dependency['status'] === 'missing')
                        <p class="text-small text-muted">No source provided. You may need to find and install this dependency yourself.</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @foreach($installation['blockers'] as $blocker)
        <p class="alert alert-warning" role="alert">{{ $blocker }}</p>
    @endforeach
    @if($installation['blockers'])
        <p class="text-muted">Installation is stopped. Resolve the issues above, then return to this review to check again.</p>
        <a class="btn btn-outline-secondary" href="/dashboard/packages/install-from-disk"><i class="fa-solid fa-rotate fa-fw" aria-hidden="true"></i> Check again</a>
    @elseif($installation['pending'])
        <form action="/dashboard/packages/install/dependencies" method="post">
            @include('partials/token.awt.php')
            <input type="hidden" name="installation_token" value="{{ $installation['token'] }}">
            <p class="alert alert-warning">Missing dependencies must be prepared before installation. Their sources may contain executable code. Only continue if you trust these publishers. Their own dependencies will also be checked.</p>
            <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirmed" value="1" required> I trust the dependency sources listed above.</label>
            <label class="d-flex gap-2 mb-3"><input type="checkbox" name="skip_prompts" value="1" @if($installation['skip']) checked @endif> Don’t prompt me again for dependency installation during this installation.</label>
            <p class="text-small text-muted">When selected, nested dependencies with source URLs are prepared automatically. You still confirm the final installation.</p>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-download fa-fw" aria-hidden="true"></i> Prepare dependencies from sources</button>
        </form>
    @else
        <form action="/dashboard/packages/install" method="post">
            @include('partials/token.awt.php')
            <input type="hidden" name="installation_token" value="{{ $installation['token'] }}">
            <p class="alert alert-warning">All dependencies are available. Installing these packages can execute code and access your site’s files and database. Back up your site and only install packages from sources you trust.</p>
            <p class="text-small text-muted">Installation order: {{ implode(' → ', $installation['order']) }}. New packages start disabled; enable dependencies before the package that needs them. Packages installed before a later failure are retained.</p>
            <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirmed" value="1" required> I trust these packages and want to install them.</label>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-download fa-fw" aria-hidden="true"></i> Install {{ count($installation['order']) > 1 ? 'packages' : 'package' }}</button>
        </form>
    @endif
    <form class="pm-cancel-install" action="/dashboard/packages/install/cancel" method="post">
        @include('partials/token.awt.php')
        <input type="hidden" name="installation_token" value="{{ $installation['token'] }}">
        <button class="btn btn-ghost" type="submit">Cancel and choose another ZIP</button>
    </form>
</section>
