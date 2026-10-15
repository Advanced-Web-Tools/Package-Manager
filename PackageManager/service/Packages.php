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
        return $this->locked(function () use ($actor, $path): InstalledPackage {
            $manifest = null;
            $audit = $this->log->begin($actor, 'Uploaded archive', 'install');
            try {
                $manifest = $this->archive->inspect($path);
                if ($this->facade->getPackage($manifest['name'])) throw new \InvalidArgumentException('This package is already installed.');
                $name = $this->archive->install($path);
                $package = $this->facade->getPackage($name) ?? throw new \RuntimeException('Installation finished but the package record could not be read.');
            } catch (\Throwable $error) {
                $this->log->finish($audit, 'failed', $error->getMessage(), $manifest['name'] ?? null);
                throw $error;
            }
            $this->log->finish($audit, 'success', $name . ' installed, disabled until enabled.', $name);
            return $package;
        });
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
