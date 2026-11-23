<article class="card pm-market-overview pm-market-package">
    <div class="pm-market-hero-art">
        @include('partials/marketplace-cover.awt.php', ['item' => $item, 'mediaClass' => 'pm-market-preview'])
    </div>
    <div class="pm-market-overview-copy">
        <div class="pm-market-package-heading">
            <div class="theme-heading">
                <div class="avatar-tile theme-icon-large">
                    <img src="{{ $item['logo'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="">
                </div>
                <div>
                    <h1 class="h2">{{ $item['name'] }}</h1>
                    <p class="pm-market-publisher text-muted">Published by <a href="{{ $item['owner_url'] }}">{{ $item['publisher'] ?? ucfirst($item['owner']['type']) }}</a></p>
                    <div class="pm-market-tags">
                        @foreach($item['tags'] as $tag)
                            <a class="badge badge-muted" href="/dashboard/packages/browse?tag={{ $tag['id'] }}">{{ $tag['name'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="pm-market-heading-actions">
                @if($item['installed'])
                    <button class="btn btn-outline-secondary pm-market-installed" type="button" disabled>Installed</button>
                    @if(!empty($item['update_url']))
                        <a class="btn btn-primary" href="{{ $item['update_url'] }}">Update</a>
                    @endif
                @elseif($item['operation'] === 'Install on server')
                    <a class="btn btn-primary" href="#pm-release-action">Install</a>
                @endif
            </div>
        </div>
        <dl class="pm-market-facts">
            <div><dt>Latest compatible stable version</dt><dd>{{ $item['release']['version'] ?? 'Unavailable' }}</dd></div>
            <div>
                <dt>Rating</dt>
                <dd>{{ $item['rating']['count'] ? number_format($item['rating']['score'], 1) . ' (' . $item['rating']['count'] . ($item['rating']['count'] === 1 ? ' review)' : ' reviews)') : 'No reviews yet' }}</dd>
                <dd><a href="{{ $item['rating_url'] }}" target="_blank" rel="noopener noreferrer">Rate on Marketplace <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></dd>
            </div>
        </dl>
    </div>
</article>
@include('partials/marketplace-gallery.awt.php', ['gallery' => $item['gallery'] ?? null])
<section class="card" aria-labelledby="pm-market-description">
    <h2 id="pm-market-description" class="h3">Description</h2>
    @include('partials/description.awt.php', ['descriptionHtml' => $item['description_markup'] ?? (new \PackageManager\service\PackageReadme())->description($item['description']) ?: '<p>No description provided</p>'])
</section>

<section class="card pm-market-release" aria-labelledby="pm-release-title">
    <div class="page-heading"><h2 class="h3" id="pm-release-title">Versions</h2></div>
    @if($item['versions'])
        <form method="get" action="/dashboard/packages/browse#pm-release-title" class="pm-filters pm-market-version-picker">
            <input type="hidden" name="package" value="{{ $item['id'] }}">
            <input type="hidden" name="page" value="{{ $item['page'] }}">
            <div class="pm-type-field">
                <label class="form-label" for="pm-release">Compatible version</label>
                <select class="form-select" id="pm-release" name="release">
                    @foreach($item['versions'] as $version)
                        <option value="{{ $version['id'] }}" @if($item['selected']['id'] === $version['id']) selected @endif>{{ $version['version'] }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline-primary" type="submit">View release</button>
        </form>
        <div class="pm-market-release-grid">
            <div class="pm-market-action" id="pm-release-action">
                <dl class="pm-market-version-pair">
                    <div>
                        <dt>Installed</dt>
                        <dd>{{ $item['installed']->version ?? 'None' }}</dd>
                        @if($item['installed'])
                            <dd><a class="text-small" href="/dashboard/packages/{{ $item['installed']->id }}">Manage package</a></dd>
                        @endif
                    </div>
                    <div><dt>Selected release</dt><dd>{{ $item['selected']['version'] }}</dd></div>
                </dl>
                @if($item['operation'])
                    @if(!empty($item['manifest']['system_package']) && !$item['installed'])
                        <p class="text-muted">AWT enables this system package immediately after installation.</p>
                    @endif
                    <form action="/dashboard/packages/browse/install" method="post" id="pm-market-install" class="pm-market-install">
                        @include('partials/token.awt.php')
                        <input type="hidden" name="storefront_id" value="{{ $item['id'] }}">
                        <input type="hidden" name="release_id" value="{{ $item['selected']['id'] }}">
                        <input type="hidden" name="release_page" value="{{ $item['page'] }}">
                        @if($item['installed'])
                            <input type="hidden" name="installed_id" value="{{ $item['installed']->id }}">
                            <input type="hidden" name="installed_version" value="{{ $item['installed']->version }}">
                        @endif
                        <label class="pm-market-confirm"><input type="checkbox" name="confirmed" value="1" required><span>I trust this release and its dependencies to run on this server</span></label>
                        <div class="form-actions">
                            @if($item['operation'] === 'Downgrade')
                                <button class="btn btn-danger" type="button" data-open-dialog="pm-downgrade-dialog">Downgrade</button>
                                <dialog class="pm-dialog" id="pm-downgrade-dialog" aria-labelledby="pm-downgrade-title">
                                    <h2 class="h4" id="pm-downgrade-title">Downgrade {{ $item['name'] }}?</h2>
                                    <p>Switch from {{ $item['installed']->version }} to {{ $item['selected']['version'] }}. Older releases may be incompatible with data or settings created by newer versions.</p>
                                    <div class="form-actions">
                                        <button class="btn btn-ghost" type="button" data-close-dialog autofocus>Cancel</button>
                                        <button class="btn btn-danger" type="submit" name="downgrade_confirmed" value="1" disabled>Confirm downgrade</button>
                                    </div>
                                </dialog>
                            @else
                                <button class="btn btn-primary" type="submit">{{ $item['operation'] }}</button>
                            @endif
                        </div>
                    </form>
                @elseif($item['installed'] && version_compare($item['selected']['version'], $item['installed']->version) === 0)
                    <p class="text-muted mb-0">This version is installed.</p>
                @else
                    <p class="text-muted mb-0">
                        @if(($item['manifest']['type'] ?? 0) === 0)
                            Framework packages use the server deployment workflow.
                        @elseif($item['installed'] && ($item['manifest']['type'] !== $item['installed']->type || !empty($item['manifest']['system_package']) !== $item['installed']->system_package))
                            This release changes the installed package type or system status.
                        @else
                            This package was installed from a different Marketplace storefront.
                        @endif
                    </p>
                @endif
            </div>
            <div class="pm-market-release-metadata">
                <h3 class="h5">Release metadata</h3>
                <dl class="pm-market-facts">
                    <div><dt>Package name</dt><dd>{{ $item['manifest']['name'] }}</dd></div>
                    <div><dt>Published</dt><dd>{{ $item['selected']['published_at'] }}</dd></div>
                    <div><dt>AWT compatibility</dt><dd>{{ $item['selected']['minimum_awt_version'] }}{{ !empty($item['selected']['maximum_awt_version']) ? ' – ' . $item['selected']['maximum_awt_version'] : ' or later' }}</dd></div>
                    <div><dt>Author</dt><dd>{{ $item['manifest']['author'] ?? 'Not specified' }}</dd></div>
                    <div><dt>License</dt><dd>{{ $item['manifest']['license'] ?? 'Not specified' }}</dd></div>
                </dl>
                <details class="pm-manifest">
                    <summary>View release manifest</summary>
                    <pre>{{ json_encode($item['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        </div>
    @else
        <p class="text-muted mb-0">No compatible releases on this page</p>
        @if($item['installed'])
            <a href="/dashboard/packages/{{ $item['installed']->id }}">Manage installed package</a>
        @endif
    @endif
    @if($item['previous'] || $item['next'])
        <nav class="pm-log-pagination" aria-label="Version pages">
            @if($item['previous']) <a class="btn btn-outline-secondary" href="{{ $item['previous'] }}">Previous</a> @endif
            <span class="text-small text-muted">Page {{ $item['page'] }}</span>
            @if($item['next']) <a class="btn btn-outline-secondary" href="{{ $item['next'] }}">Next</a> @endif
        </nav>
    @endif
</section>
