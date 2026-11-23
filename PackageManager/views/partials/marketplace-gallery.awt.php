@if($gallery)
    <section class="pm-market-gallery" aria-labelledby="pm-gallery-title">
        <div class="pm-market-section-heading"><h2 class="h3" id="pm-gallery-title">Gallery</h2></div>
        @include('partials/description.awt.php', ['descriptionHtml' => $gallery['description_markup']])
        <div class="pm-market-gallery-grid">
            @foreach($gallery['items'] as $index => $media)
                @if($media['type'] === 'video')
                    <video controls playsinline preload="none" src="{{ $media['url'] }}" aria-label="Gallery video {{ $index + 1 }}"></video>
                @else
                    <a href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer" data-gallery-image aria-label="Open gallery image {{ $index + 1 }}">
                        <img loading="lazy" src="{{ $media['url'] }}" alt="Gallery image {{ $index + 1 }}">
                    </a>
                @endif
            @endforeach
        </div>
        @if(in_array('image', array_column($gallery['items'], 'type'), true))
            <dialog class="pm-dialog pm-gallery-viewer" aria-labelledby="pm-gallery-viewer-title">
                <header class="pm-gallery-viewer-header">
                    <h2 id="pm-gallery-viewer-title" class="h5">Gallery</h2>
                    <span class="text-small text-muted" data-gallery-position aria-live="polite"></span>
                    <button class="btn btn-ghost" type="button" data-close-dialog autofocus>Close</button>
                </header>
                <div class="pm-gallery-viewer-image">
                    <img alt="">
                </div>
                @if(count(array_keys(array_column($gallery['items'], 'type'), 'image', true)) > 1)
                    <div class="pm-gallery-viewer-navigation">
                        <button class="btn btn-outline-secondary" type="button" data-gallery-step="-1" aria-label="Previous image"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                        <button class="btn btn-outline-secondary" type="button" data-gallery-step="1" aria-label="Next image"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    </div>
                @endif
            </dialog>
        @endif
    </section>
@endif
