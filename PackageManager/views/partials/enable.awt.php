@if($enablePrompt)
    <section class="card mb-4" aria-labelledby="pm-enable-inline-title">
        <h2 id="pm-enable-inline-title" class="h4">Enable {{ $enablePrompt['name'] }}?</h2>
        <p>The package was installed successfully and is currently disabled.</p>
        <div class="form-actions">
            <a class="btn btn-outline-secondary" href="/dashboard/packages">Keep disabled</a>
            <form method="post" action="/dashboard/packages/action">
                @include('partials/token.awt.php')
                <input type="hidden" name="package_id" value="{{ $enablePrompt['id'] }}">
                <input type="hidden" name="operation" value="enable">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-power-off fa-fw" aria-hidden="true"></i> Enable package</button>
            </form>
        </div>
    </section>
    <dialog id="pm-enable-dialog" class="pm-dialog" aria-labelledby="pm-enable-title" data-auto-open>
        <h2 id="pm-enable-title" class="h3">Enable {{ $enablePrompt['name'] }}?</h2>
        <p>Installation succeeded. Enable the package now, or keep it disabled until you are ready.</p>
        <div class="form-actions">
            <a class="btn btn-outline-secondary" href="/dashboard/packages" autofocus>Keep disabled</a>
            <form method="post" action="/dashboard/packages/action">
                @include('partials/token.awt.php')
                <input type="hidden" name="package_id" value="{{ $enablePrompt['id'] }}">
                <input type="hidden" name="operation" value="enable">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-power-off fa-fw" aria-hidden="true"></i> Enable package</button>
            </form>
        </div>
    </dialog>
@endif
