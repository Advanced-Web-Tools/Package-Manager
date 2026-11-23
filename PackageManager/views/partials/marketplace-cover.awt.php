@if($item['video'])
    <video class="{{ $mediaClass }}" muted loop playsinline preload="none" data-market-preview poster="{{ $item['cover'] }}" src="{{ $item['video'] }}" aria-hidden="true"></video>
    <img class="{{ $mediaClass }}" src="{{ $item['cover'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="" loading="lazy" hidden>
@else
    <img class="{{ $mediaClass }}" src="{{ $item['cover'] }}" data-image-fallback="/awt_packages/PackageManager/views/assets/images/default_preview.webp" alt="" loading="lazy">
@endif
