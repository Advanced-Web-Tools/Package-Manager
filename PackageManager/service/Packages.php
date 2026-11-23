<?php

namespace PackageManager\service;

use admin\Admin;
use package\dependency\Dependency;
use package\facade\PackageFacade;
use package\model\InstalledPackage;
use vfs\transient\TransientStorage;

final class Packages
{
    public function __construct(
        private readonly PackageFacade $facade = new PackageFacade(),
        private readonly ActionLog $log = new ActionLog(),
        private readonly PackageArchive $archive = new PackageArchive(),
    ) {}

    public function all(Admin $actor): array
    {
        Access::actor($actor);
        $packages = $this->facade->getInstalled();
        usort($packages, static fn($a, $b) => strnatcasecmp($a->name, $b->name));
        return $packages;
    }

    public static function typeLabel(int $type): string
    {
        return match ($type) { 1 => 'Plugins', 2 => 'Themes', default => 'Unknown' };
    }

    public static function typeKey(int $type): string
    {
        return match ($type) { 1 => 'plugins', 2 => 'themes', default => 'unknown' };
    }

    /** Filter the directory only; dependency checks still see every installed package. */
    public function catalog(Admin $actor, string $query = '', string $type = ''): array
    {
        $query = trim($query);
        if (!preg_match('/^.{0,200}$/us', $query)) {
            throw new \InvalidArgumentException('Search must contain at most 200 characters of valid text.');
        }
        $visible = array_values(array_filter($this->all($actor), static fn($package) => $package->type !== 0));
        $types = [];
        foreach ($visible as $package) $types[self::typeKey($package->type)] = self::typeLabel($package->type);
        $types = array_intersect_key(['plugins' => 'Plugins', 'themes' => 'Themes', 'unknown' => 'Unknown'], $types);
        if (!array_key_exists($type, $types)) $type = '';
        $packages = array_values(array_filter($visible, static function ($package) use ($query, $type): bool {
            if ($type !== '' && self::typeKey($package->type) !== $type) return false;
            $text = implode(' ', [$package->name, $package->description ?? '', $package->author ?? '', $package->version, self::typeLabel($package->type)]);
            return $query === '' || preg_match('/' . preg_quote($query, '/') . '/iu', $text) === 1;
        }));
        return ['packages' => $packages, 'packageTypes' => $types, 'search' => $query, 'typeFilter' => $type, 'totalPackages' => count($visible)];
    }

    public function find(Admin $actor, int $id): InstalledPackage
    {
        Access::actor($actor);
        if ($id < 1) throw new \InvalidArgumentException('Choose a valid package.');
        return $this->facade->getPackageById($id);
    }

    /** Use the framework's hydrated database record as the sole metadata source. */
    public function metadata(InstalledPackage $package): array
    {
        $metadata = $package->toArray();
        unset($metadata['dependenciesCollection'], $metadata['id'], $metadata['status']);
        $metadata['dependencies'] = $package->getDependencies();
        return $metadata;
    }

    /** Resolve dependency state from current installed records, as ThemeEngine does. */
    public function dependencies(Admin $actor, InstalledPackage $package): array
    {
        $installed = [];
        foreach ($this->all($actor) as $candidate) $installed[$candidate->name] = $candidate;
        $dependencies = $package->getDependencies();
        foreach ($dependencies as &$dependency) {
            $candidate = $installed[$dependency['name']] ?? null;
            $dependency['installed_version'] = $candidate?->version;
            $dependency['enabled'] = $candidate?->status ?? false;
            if ($candidate === null) {
                $status = 'missing';
            } else {
                try {
                    $status = !Dependency::matchesVersion($candidate->version, $dependency['version'])
                        ? 'incompatible' : ($candidate->status ? 'installed' : 'disabled');
                } catch (\InvalidArgumentException) { $status = 'unknown'; }
            }
            $dependency['status'] = $status;
            $dependency['status_label'] = match ($status) {
                'installed' => 'Installed and compatible', 'missing' => 'Missing',
                'disabled' => 'Disabled', 'incompatible' => 'Incompatible version',
                default => 'Version requirement could not be checked',
            };
        }
        return $dependencies;
    }

    public static function image(InstalledPackage $package, string $field): string
    {
        return ($field === 'icon' ? $package->icon : $package->preview_image)
            ?: '/awt_packages/PackageManager/views/assets/images/default_' . ($field === 'icon' ? 'icon' : 'preview') . '.webp';
    }

    public static function licenseUrl(?string $url): ?string
    {
        return $url && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['https', 'http'], true) ? $url : null;
    }

    /** Framework status changes do not validate dependency safety. */
    public function validateAction(InstalledPackage $package, string $action, array $installed): void
    {
        if (!in_array($action, ['enable', 'disable', 'remove'], true)) throw new \InvalidArgumentException('Choose enable, disable or remove.');
        if ($action !== 'enable' && ($package->system_package || $package->type === 0)) {
            throw new \InvalidArgumentException('System packages cannot be disabled or removed.');
        }
        $blockers = [];
        if ($action === 'enable') {
            foreach ($package->getDependencies() as $dependency) {
                $match = null;
                foreach ($installed as $candidate) {
                    if ($candidate->name === $dependency['name']) $match = $candidate;
                }
                if (!$match || !$match->status || !Dependency::matchesVersion($match->version, $dependency['version'])) {
                    $blockers[] = $dependency['name'] . ' ' . $dependency['version'] . ' (must be installed, compatible and enabled)';
                }
            }
            if (defined('AWT_VERSION') && (version_compare(AWT_VERSION, $package->minimum_awt_version, '<')
                || ($package->maximum_awt_version && version_compare(AWT_VERSION, $package->maximum_awt_version, '>')))) {
                throw new \InvalidArgumentException('This package is incompatible with the running AWT version.');
            }
        } else {
            foreach ($installed as $candidate) {
                if ($candidate->id === $package->id || ($action === 'disable' && !$candidate->status)) continue;
                foreach ($candidate->getDependencies() as $dependency) {
                    if ($dependency['name'] === $package->name) $blockers[] = $candidate->name;
                }
            }
        }
        if ($blockers) {
            throw new \InvalidArgumentException(($action === 'enable' ? 'Required dependencies: ' : 'Packages that rely on this package: ') . implode(', ', array_unique($blockers)) . '.');
        }
    }

    public function change(Admin $actor, int $id, string $action): string
    {
        Access::actor($actor);
        return $this->locked(function () use ($actor, $id, $action): string {
            $package = $this->find($actor, $id);
            $audit = $this->log->begin($actor, $package->name, $action);
            try {
                $this->validateAction($package, $action, $this->all($actor));
                match ($action) {
                    'enable' => $this->facade->enablePackage($id),
                    'disable' => $this->facade->disablePackage($id),
                    'remove' => $this->facade->removePackage($id),
                };
                $message = $package->name . ' ' . match ($action) {
                    'enable' => 'enabled.', 'disable' => 'disabled.', 'remove' => 'removed. Owned storage and tables were retained.',
                };
            } catch (\Throwable $error) {
                $this->log->finish($audit, 'failed', $error->getMessage());
                throw $error;
            }
            $this->log->finish($audit, 'success', $message);
            return $message;
        });
    }

    public function install(Admin $actor, string $path, bool $confirmed): InstalledPackage
    {
        Access::actor($actor);
        if (!$confirmed) throw new \InvalidArgumentException('Confirm that you trust this package source before installing.');
        return $this->locked(fn() => $this->installArchive($actor, $path));
    }

    public function installMarketplace(Admin $actor, array $plan, ?InstalledPackage $expected = null, bool $downgradeConfirmed = false): InstalledPackage
    {
        Access::administrator($actor);
        return $this->locked(function () use ($actor, $plan, $expected, $downgradeConfirmed): InstalledPackage {
            $root = array_key_last($plan);
            $current = $this->facade->getPackage($root);
            if ($current?->id !== $expected?->id || $current?->version !== $expected?->version) {
                throw new \RuntimeException('The installed version changed. Review the package again.');
            }
            $action = 'install';
            if ($current) {
                $manifest = $plan[$root]['manifest'];
                $action = $this->validateVersionChange($actor, $current, $manifest, $plan[$root]['source'], $downgradeConfirmed);
            }
            foreach ($plan as $entry) {
                if (empty($entry['manifest']['system_package'])) continue;
                foreach ($entry['manifest']['dependencies'] as $requirement) {
                    $dependency = Dependency::fromArray($requirement);
                    $installed = $this->facade->getPackage($dependency->name);
                    $planned = $plan[$dependency->name]['manifest'] ?? [];
                    $version = $installed ? ($installed->status ? $installed->version : null)
                        : (!empty($planned['system_package']) ? $planned['version'] : null);
                    if ($version === null || !Dependency::matchesVersion($version, $dependency->version)) {
                        throw new \RuntimeException('Enable a compatible version of ' . $dependency->name . ' before applying ' . $entry['manifest']['name'] . '.');
                    }
                }
            }
            foreach ($plan as $name => $entry) {
                if ($name !== $root && $this->facade->getPackage($name)) continue;
                $package = $this->installArchive($actor, $entry['path'], $entry['source'], $name === $root ? $action : 'install');
            }
            return $package;
        });
    }

    private function validateVersionChange(Admin $actor, InstalledPackage $current, array $manifest, array $source, bool $downgradeConfirmed): string
    {
        if ($current->type !== $manifest['type'] || $current->system_package !== !empty($manifest['system_package'])) {
            throw new \RuntimeException('This installed package cannot change versions through Marketplace.');
        }
        $registered = MarketplacePackageRepository::find($current->id);
        if ($registered && $registered['latest_url'] !== $source['latest_url']) {
            throw new \RuntimeException('This package was installed from a different Marketplace storefront.');
        }
        $direction = version_compare($manifest['version'], $current->version);
        if ($direction === 0) throw new \RuntimeException('This version is already installed.');
        if ($direction < 0 && !$downgradeConfirmed) throw new \RuntimeException('Confirm the downgrade warning before continuing.');
        foreach ($this->all($actor) as $candidate) {
            foreach ($candidate->getDependencies() as $requirement) {
                $dependency = Dependency::fromArray($requirement);
                if ($dependency->name === $current->name && !Dependency::matchesVersion($manifest['version'], $dependency->version)) {
                    throw new \RuntimeException($candidate->name . ' requires ' . $current->name . ' ' . $dependency->version . '.');
                }
            }
        }
        if ($current->status && !$current->system_package) {
            foreach ($manifest['dependencies'] as $requirement) {
                $dependency = Dependency::fromArray($requirement);
                $installed = $this->facade->getPackage($dependency->name);
                if (!$installed?->status || !Dependency::matchesVersion($installed->version, $dependency->version)) {
                    throw new \RuntimeException('Enable a compatible version of ' . $dependency->name . ' before changing versions.');
                }
            }
        }
        return $direction > 0 ? 'update' : 'downgrade';
    }

    private function installArchive(Admin $actor, string $path, ?array $source = null, string $action = 'install'): InstalledPackage
    {
        $manifest = null;
        $audit = $this->log->begin($actor, $source === null ? 'Uploaded archive' : 'Marketplace archive', $action);
        try {
            $manifest = $this->archive->inspect($path, $source !== null);
            if ($action === 'install' && $this->facade->getPackage($manifest['name'])) throw new \InvalidArgumentException('This package is already installed.');
            $name = $this->archive->install($path, $source, $action !== 'install');
            $package = $this->facade->getPackage($name) ?? throw new \RuntimeException('Installation finished but the package record could not be read.');
        } catch (\Throwable $error) {
            $this->log->finish($audit, 'failed', $error->getMessage(), $manifest['name'] ?? null);
            throw $error;
        }
        $message = $action === 'install' ? $name . ($package->status ? ' installed and enabled.' : ' installed, disabled until enabled.') : $name . ' switched to ' . $package->version . '.';
        $this->log->finish($audit, 'success', $message, $name);
        return $package;
    }

    private function locked(callable $operation): mixed
    {
        (new TransientStorage())->setPool('temp')->setSubPool('PackageManager');
        // Open one stable inode: replacing a lock file would let concurrent requests bypass it.
        $handle = fopen(DATA . 'storage/framework/temp/PackageManager/actions.lock', 'c');
        if (!$handle) throw new \RuntimeException('Unable to open the package action lock.');
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Another package action is running. Try again shortly.');
            return $operation();
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
