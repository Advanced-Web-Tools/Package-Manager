<?php

namespace PackageManager\service;

use admin\Admin;
use package\dependency\Dependency;
use package\facade\PackageFacade;
use package\model\InstalledPackage;
use vfs\transient\TransientStorage;

/** Session-owned, expiring archives; every manifest is rechecked before installation. */
final class InstallationPlan
{
    private const MAX_PACKAGES = 30;
    private const MAX_TOTAL_BYTES = 200 * 1024 * 1024;
    private const LIFETIME = 3600;

    public function __construct(
        private readonly PackageArchive $archive = new PackageArchive(),
        private readonly PackageFacade $facade = new PackageFacade(),
        private readonly DependencyDownload $download = new DependencyDownload(),
    ) {}

    public function stage(Admin $actor, string $path): array
    {
        Access::administrator($actor);
        $manifest = $this->archive->inspect($path);
        $this->validateManifest($manifest);
        $this->discard();
        $token = bin2hex(random_bytes(24));
        $_SESSION['packagemanager_installation'] = ['token' => $token, 'expires' => time() + self::LIFETIME,
            'root' => $manifest['name'], 'files' => [], 'skip' => false, 'actor' => $actor->getKey()];
        try {
            $stored = $this->directory() . '/' . bin2hex(random_bytes(16)) . '.zip';
            if (!copy($path, $stored)) { if (is_file($stored)) unlink($stored); throw new \RuntimeException('Unable to retain the uploaded archive for review.'); }
            $_SESSION['packagemanager_installation']['files'][$manifest['name']] = $stored;
            return $this->review($actor);
        } catch (\Throwable $error) { $this->discard(); throw $error; }
    }

    public function review(Admin $actor): ?array
    {
        Access::administrator($actor);
        $state = $_SESSION['packagemanager_installation'] ?? null;
        if (!$state) return null;
        if ($state['expires'] < time() || $state['actor'] !== $actor->getKey()) {
            $this->discard();
            throw new \RuntimeException('This installation review expired. Choose the ZIP again.');
        }
        $nodes = [];
        $bytes = 0;
        foreach ($state['files'] as $name => $path) {
            $manifest = $this->archive->inspect($path);
            if ($manifest['name'] !== $name) throw new \RuntimeException('A staged package changed. Choose the ZIP again.');
            $this->validateManifest($manifest, $name !== $state['root']);
            $nodes[$name] = $manifest;
            $bytes += filesize($path);
        }
        if ($bytes > self::MAX_TOTAL_BYTES) throw new \RuntimeException('Dependency archives exceed the 200 MB review limit.');
        $rows = []; $pending = []; $blockers = []; $order = []; $visiting = []; $visited = [];
        $visit = function (string $name) use (&$visit, &$rows, &$pending, &$blockers, &$order, &$visiting, &$visited, $nodes): void {
            if (isset($visiting[$name])) { $blockers[] = 'Circular dependency involving ' . $name . '.'; return; }
            if (isset($visited[$name])) return;
            $visiting[$name] = true;
            foreach ($nodes[$name]['dependencies'] as $requirement) {
                $dependency = Dependency::fromArray($requirement);
                $installed = $this->facade->getPackage($dependency->name);
                $source = Packages::licenseUrl($dependency->url);
                $version = $installed?->version ?? ($nodes[$dependency->name]['version'] ?? null);
                $status = $version === null ? 'missing' : (!Dependency::matchesVersion($version, $dependency->version) ? 'incompatible' : ($installed ? 'installed' : 'prepared'));
                $rows[] = ['parent' => $name, 'name' => $dependency->name, 'version' => $dependency->version,
                    'installed_version' => $installed?->version, 'status' => $status, 'url' => $source];
                if ($status === 'incompatible') {
                    $blockers[] = $dependency->name . ' requires ' . $dependency->version . '; found ' . $version . '. Install a compatible version manually.';
                } elseif ($status === 'missing') {
                    if (!$source) $blockers[] = 'No download source for ' . $dependency->name . '. You may need to find and install it yourself before continuing.';
                    else $pending[$dependency->name] = ['name' => $dependency->name, 'version' => $dependency->version, 'url' => $source];
                } elseif (!$installed) $visit($dependency->name);
            }
            unset($visiting[$name]); $visited[$name] = true; $order[] = $name;
        };
        $visit($state['root']);
        $destinations = [];
        foreach (array_keys($nodes) as $name) {
            $directoryName = strtolower(str_replace(' ', '', $name));
            if (isset($destinations[$directoryName])) $blockers[] = 'Packages ' . $destinations[$directoryName] . ' and ' . $name . ' share a package directory. Resolve this manually.';
            $destinations[$directoryName] = $name;
        }
        if (count($nodes) + count($pending) > self::MAX_PACKAGES) $blockers[] = 'Dependency chain exceeds the 30 package limit.';
        return ['token' => $state['token'], 'manifest' => $nodes[$state['root']], 'nodes' => $nodes, 'dependencies' => $rows,
            'pending' => array_values($pending), 'blockers' => array_values(array_unique($blockers)), 'order' => $order,
            'ready' => !$pending && !$blockers, 'skip' => $state['skip']];
    }

    /** Inspect one dependency level per confirmation, or the whole chain when requested. */
    public function prepare(Admin $actor, string $token, bool $confirmed, bool $skip): array
    {
        $plan = $this->requirePlan($actor, $token);
        if (!$confirmed) throw new \InvalidArgumentException('Confirm that you trust the listed dependency sources.');
        $_SESSION['packagemanager_installation']['skip'] = $skip;
        do {
            if ($plan['blockers'] || !$plan['pending']) return $plan;
            foreach ($plan['pending'] as $dependency) {
                $path = $this->directory() . '/' . bin2hex(random_bytes(16)) . '.zip';
                try {
                    $this->download->fetch($dependency['url'], $path);
                    $manifest = $this->archive->inspect($path);
                    if ($manifest['name'] !== $dependency['name'] || !Dependency::matchesVersion($manifest['version'], $dependency['version'])) {
                        throw new \RuntimeException('Source for ' . $dependency['name'] . ' returned a different package or incompatible version.');
                    }
                    $this->validateManifest($manifest);
                    $files = $_SESSION['packagemanager_installation']['files'];
                    $total = filesize($path);
                    foreach ($files as $file) $total += filesize($file);
                    if (count($files) >= self::MAX_PACKAGES || $total > self::MAX_TOTAL_BYTES) {
                        throw new \RuntimeException('Dependency chain exceeds the 30 package or 200 MB limit.');
                    }
                    $_SESSION['packagemanager_installation']['files'][$manifest['name']] = $path;
                } finally {
                    if (!in_array($path, $_SESSION['packagemanager_installation']['files'], true) && is_file($path)) unlink($path);
                }
            }
            $plan = $this->review($actor);
        } while ($skip && $plan['pending'] && !$plan['blockers']);
        return $plan;
    }

    public function install(Admin $actor, string $token, bool $confirmed): InstalledPackage
    {
        $plan = $this->requirePlan($actor, $token);
        if (!$confirmed) throw new \InvalidArgumentException('Confirm that you trust these packages before installing.');
        if (!$plan['ready']) throw new \RuntimeException('Resolve every missing or incompatible dependency before installing.');
        $service = new Packages();
        try {
            foreach ($plan['order'] as $name) {
                if ($name !== $plan['manifest']['name'] && $this->facade->getPackage($name)) continue;
                $service->install($actor, $_SESSION['packagemanager_installation']['files'][$name], true);
            }
            return $this->facade->getPackage($plan['manifest']['name'])
                ?? throw new \RuntimeException('Installation completed but the package record could not be read.');
        } finally {
            // A failed hook may leave registered packages; do not silently skip them on retry.
            $this->discard();
        }
    }

    public function cancel(Admin $actor, string $token): void
    {
        Access::administrator($actor);
        $state = $_SESSION['packagemanager_installation'] ?? null;
        if (!$state || $state['actor'] !== $actor->getKey() || !hash_equals($state['token'], $token)) {
            throw new \RuntimeException('Installation review not found.');
        }
        $this->discard();
    }

    private function requirePlan(Admin $actor, string $token): array
    {
        $plan = $this->review($actor);
        if (!$plan || !hash_equals($plan['token'], $token)) throw new \RuntimeException('Installation review not found. Choose the ZIP again.');
        return $plan;
    }

    private function validateManifest(array $manifest, bool $allowInstalled = false): void
    {
        $installed = $this->facade->getPackage($manifest['name']);
        if (!$allowInstalled && $installed) throw new \RuntimeException($manifest['name'] . ' is already installed.');
        $destination = rtrim(PACKAGES, '/') . '/' . str_replace(' ', '', $manifest['name']);
        if (!$installed && (file_exists($destination) || is_link($destination))) {
            throw new \RuntimeException('A package directory for ' . $manifest['name'] . ' already exists. Resolve it before installing.');
        }
        if (defined('AWT_VERSION') && (version_compare(AWT_VERSION, $manifest['minimum_awt_version'], '<')
            || (!empty($manifest['maximum_awt_version']) && version_compare(AWT_VERSION, $manifest['maximum_awt_version'], '>')))) {
            throw new \RuntimeException($manifest['name'] . ' is incompatible with this AWT version.');
        }
    }

    private function directory(): string
    {
        (new TransientStorage())->setPool('temp')->setSubPool('PackageManager/reviews');
        $directory = DATA . 'storage/framework/temp/PackageManager/reviews';
        foreach (glob($directory . '/*.zip') ?: [] as $file) {
            if (filemtime($file) < time() - self::LIFETIME) unlink($file);
        }
        return $directory;
    }

    private function discard(): void
    {
        foreach (($_SESSION['packagemanager_installation']['files'] ?? []) as $path) if (is_file($path)) unlink($path);
        unset($_SESSION['packagemanager_installation']);
    }
}
