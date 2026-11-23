<article class="card pm-market-overview">
    <div class="pm-market-hero-art">
        @include('partials/marketplace-cover.awt.php', ['item' => $profile, 'mediaClass' => 'pm-market-preview'])
    </div>
    <div class="pm-market-overview-copy">
        <div class="theme-heading">
            <div class="avatar-tile theme-icon-large">
                @if($profile['type'] === 'creator' && empty($profile['avatar']))
                    <span class="pm-publisher-initial" aria-hidden="true">{{ preg_match('/^./us', $profile['name'], $initial) ? $initial[0] : '' }}</span>
                @else
                    <img src="{{ $profile['avatar'] ?? $profile['logo'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="">
                @endif
            </div>
            <div>
                <h1 class="h2">{{ $profile['name'] }}</h1>
                <span class="text-small text-muted">{{ ucfirst($profile['type']) }}@if(!empty($profile['username'])) · {{ '@' . $profile['username'] }}@endif</span>
            </div>
        </div>
        @if($profile['links'])
            <div class="form-actions">
                @foreach($profile['links'] as $link)
                    <a class="btn btn-outline-secondary" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ $link['label'] }} <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                @endforeach
            </div>
        @endif
    </div>
</article>
@include('partials/marketplace-gallery.awt.php', ['gallery' => $profile['gallery'] ?? null])
@if(!empty($profile['description_markup']) || !empty($profile['description']))
    <section class="card" aria-labelledby="pm-profile-description">
        <h2 id="pm-profile-description" class="h3">About</h2>
        @include('partials/description.awt.php', ['descriptionHtml' => $profile['description_markup'] ?? (new \PackageManager\service\PackageReadme())->description($profile['description'])])
    </section>
@endif
@if(!empty($profile['relationships']))
    <section aria-labelledby="pm-profile-relationships">
        <div class="pm-market-section-heading"><h2 class="h3" id="pm-profile-relationships">{{ $profile['type'] === 'creator' ? 'Organizations' : 'Members' }}</h2></div>
        <ul class="pm-profile-relationships">
            @foreach($profile['relationships'] as $related)
                <li>
                    <a href="{{ $related['url'] }}">
                        <img class="pm-market-logo" src="{{ $related['image'] ?? '/awt_packages/PackageManager/views/assets/images/default_icon.webp' }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_icon.webp" alt="" loading="lazy">
                        <span>{{ $related['name'] }}
                            @if($related['meta']) <small class="text-muted">{{ $related['meta'] }}</small> @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
<section aria-labelledby="pm-owner-packages">
    <div class="pm-market-section-heading"><h2 class="h3" id="pm-owner-packages">Packages</h2></div>
    @if($profile['items'])
        <div class="card-grid pm-market-grid">
            @foreach($profile['items'] as $item)
                @include('partials/marketplace-card.awt.php', ['item' => $item])
            @endforeach
        </div>
    @else
        <div class="card empty-state"><p>No compatible packages on this page</p></div>
    @endif
</section>
