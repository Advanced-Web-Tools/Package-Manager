@if(count($dependencies) === 0)
    <p class="text-muted">No package dependencies.</p>
@else
    <ul class="theme-dependencies">
        @foreach($dependencies as $dependency)
            <li>
                <span>{{ $dependency['name'] }}</span> <code>{{ $dependency['version'] }}</code>
                <span class="badge theme-dependency-status theme-dependency-{{ $dependency['status'] }}">{{ $dependency['status_label'] }}</span>
                @if($dependency['installed_version'])
                    <span class="text-small text-muted">Installed {{ $dependency['installed_version'] }}</span>
                @endif
                @if(!$dependency['enabled'] && $dependency['installed_version'] && $dependency['status'] !== 'disabled')
                    <span class="badge badge-muted">Package disabled</span>
                @endif
                @if(\PackageManager\service\Packages::licenseUrl($dependency['url'] ?? null))
                    <a class="text-small" href="{{ $dependency['url'] }}" target="_blank" rel="noopener noreferrer">Package source <i class="fa-solid fa-arrow-up-right-from-square fa-fw" aria-hidden="true"></i></a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
