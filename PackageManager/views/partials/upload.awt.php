<section class="card pm-install-panel" id="pm-install-package" aria-labelledby="pm-install-title-inline">
    <div class="page-heading"><h2 class="h3" id="pm-install-title-inline"><i class="fa-solid fa-file-zipper fa-fw" aria-hidden="true"></i> Choose a package ZIP</h2></div>
    <p class="text-muted">Select the ZIP you downloaded from a trusted package publisher.</p>
    <form action="/dashboard/packages/install/review" method="post" enctype="multipart/form-data" id="pm-upload-form">
        @include('partials/token.awt.php')
        <div class="pm-upload">
            <div class="pm-upload-file">
                <label for="pm-archive" class="pm-file-picker">
                    <i class="fa-solid fa-arrow-up-from-bracket fa-fw" aria-hidden="true"></i>
                    <strong id="pm-choose-label">Choose ZIP file</strong>
                    <span class="text-small text-muted" id="pm-upload-help">From your computer · Up to 50 MB</span>
                    <input id="pm-archive" name="archive" type="file" accept=".zip,application/zip" required aria-describedby="pm-upload-help pm-filename pm-upload-error">
                </label>
                <div class="pm-file-selection" id="pm-file-selection" hidden>
                    <i class="fa-solid fa-file-zipper fa-fw" aria-hidden="true"></i>
                    <span id="pm-filename" class="text-small" role="status">No file selected</span>
                    <button class="btn btn-ghost btn-sm" type="button" id="pm-clear" aria-label="Remove selected ZIP file"><i class="fa-solid fa-xmark fa-fw" aria-hidden="true"></i></button>
                </div>
                <p id="pm-upload-error" class="pm-upload-error text-small" role="alert" hidden></p>
            </div>
            <button class="btn btn-primary pm-review-install" type="submit" id="pm-install" disabled aria-describedby="pm-install-next">Review installation <i class="fa-solid fa-arrow-right fa-fw" aria-hidden="true"></i></button>
            <p class="text-small text-muted pm-install-next" id="pm-install-next">Choose a ZIP to check its manifest and dependencies. Nothing is installed yet.</p>
        </div>
        <noscript><p>Choose a ZIP and submit it to read its manifest and check dependencies before installation.</p></noscript>
    </form>
</section>
