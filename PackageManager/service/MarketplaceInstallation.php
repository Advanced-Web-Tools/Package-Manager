<?php

namespace PackageManager\service;

use admin\Admin;
use package\dependency\Dependency;
use package\manifest\reader\ManifestReader;
use package\model\InstalledPackage;
use RuntimeException;

final class MarketplaceInstallation
{
    private array $files = [];
    private array $plan = [];
    private array $visiting = [];
    private array $installed = [];
    private array $destinations = [];
    private int $bytes = 0;

    public function __construct(
        private readonly Marketplace $marketplace = new Marketplace(),
        private readonly PackageArchive $archive = new PackageArchive(),
        private readonly Packages $packages = new Packages(),
    ) {}

    public function install(
        Admin $actor,
        int $id,
        int $versionId,
        bool $confirmed,
        ?int $installedId = null,
        ?string $installedVersion = null,
        bool $downgradeConfirmed = false,
    ): InstalledPackage {
        Access::administrator($actor);
        if (!$confirmed) throw new RuntimeException('Confirm that you trust this package and its Marketplace dependencies.');
        try {
            foreach ($this->packages->all($actor) as $package) $this->installed[$package->name] = $package;
            $release = $this->marketplace->get("/package/{$id}/versions/{$versionId}");
            $this->verifyRelease($release, $id, $versionId);
            $current = $this->installed[$release['name']] ?? null;
            if ($current?->id !== $installedId || $current?->version !== $installedVersion) {
                throw new RuntimeException('The installed version changed. Review the package again.');
            }
            $this->prepare($release, $current !== null);
            return $this->packages->installMarketplace($actor, $this->plan, $current, $downgradeConfirmed);
        } finally {
            foreach ($this->files as $path) unlink($path);
            $this->files = $this->plan = $this->installed = $this->visiting = $this->destinations = [];
            $this->bytes = 0;
        }
    }

    private function prepare(array $release, bool $replace = false): void
    {
        $name = $release['name'];
        $id = $release['package_id'];
        $versionId = $release['id'];
        if (isset($this->visiting[$name])) throw new RuntimeException('Circular Marketplace dependency involving ' . $name . '.');
        if (isset($this->plan[$name])) {
            $source = $this->plan[$name]['source'];
            if ($source['storefront_id'] !== $id || $source['release_id'] !== $versionId) {
                throw new RuntimeException('Marketplace dependencies selected conflicting releases of ' . $name . '.');
            }
            return;
        }
        if (count($this->plan) + count($this->visiting) >= 30) throw new RuntimeException('Dependency chain exceeds the 30 package limit.');
        $this->visiting[$name] = true;

        $entry = $this->marketplace->get("/package/manifest/{$id}", ['version_id' => $versionId]);
        $this->verifyRelease($entry, $id, $versionId);
        $manifest = ManifestReader::validate($entry['manifest'] ?? null);
        if ($manifest['name'] !== $name || $manifest['version'] !== $release['version']
            || $manifest['type'] === 0) {
            throw new RuntimeException('Marketplace returned an unexpected or unsupported manifest for ' . $name . '.');
        }
        $this->checkCompatibility($manifest);

        $directoryName = str_replace(' ', '', $name);
        if (isset($this->destinations[strtolower($directoryName)])
            || (!$replace && file_exists(rtrim(PACKAGES, '/') . '/' . $directoryName))
            || is_link(rtrim(PACKAGES, '/') . '/' . $directoryName)) {
            throw new RuntimeException('A package directory for ' . $name . ' already exists or conflicts with another dependency.');
        }
        $this->destinations[strtolower($directoryName)] = true;

        $resolved = array_column($release['dependencies'] ?? [], null, 'name');
        foreach ($manifest['dependencies'] as $requirement) {
            $dependency = Dependency::fromArray($requirement);
            $installed = $this->installed[$dependency->name] ?? null;
            $match = $resolved[$dependency->name] ?? [];
            $candidate = $match['resolved'] ?? null;
            if (($match['ambiguous'] ?? false) || !$candidate) $candidate = [];
            $version = $installed?->version ?? ($candidate['version'] ?? null);
            if ($version === null || !Dependency::matchesVersion($version, $dependency->version)) {
                throw new RuntimeException('Marketplace could not resolve ' . $dependency->name . ' ' . $dependency->version . '. Install a compatible dependency first.');
            }
            if ($installed) continue;
            $candidateId = $candidate['package_id'] ?? null;
            $candidateVersionId = $candidate['id'] ?? null;
            if (!is_int($candidateId) || !is_int($candidateVersionId) || $candidateId < 1 || $candidateVersionId < 1) {
                throw new RuntimeException('Marketplace returned an invalid dependency release.');
            }
            $exact = $this->marketplace->get("/package/{$candidateId}/versions/{$candidateVersionId}");
            $this->verifyRelease($exact, $candidateId, $candidateVersionId);
            if ($exact['name'] !== $dependency->name || $exact['version'] !== $version) {
                throw new RuntimeException('Marketplace returned an unexpected release for ' . $dependency->name . '.');
            }
            $this->prepare($exact);
        }

        $source = $this->marketplace->source($id, $versionId);
        $path = $this->files[] = Marketplace::temporaryFile();
        $this->marketplace->download($source, $path);
        $this->bytes += filesize($path);
        if ($this->bytes > 200 * 1024 * 1024) throw new RuntimeException('Dependency archives exceed the 200 MB limit.');
        if ($this->canonical($this->archive->inspect($path, true)) !== $this->canonical($manifest)) {
            throw new RuntimeException('Downloaded manifest differs from the Marketplace manifest for ' . $name . '.');
        }
        $this->plan[$name] = compact('path', 'source', 'manifest');
        unset($this->visiting[$name]);
    }

    private function verifyRelease(array $release, int $id, int $versionId): void
    {
        if (($release['id'] ?? null) !== $versionId || ($release['package_id'] ?? null) !== $id
            || !is_string($release['name'] ?? null) || !is_string($release['version'] ?? null)) {
            throw new RuntimeException('The selected Marketplace release is unavailable or changed. Refresh Browse and try again.');
        }
        $this->checkCompatibility($release);
    }

    private function checkCompatibility(array $data): void
    {
        if (!defined('AWT_VERSION')) return;
        $minimum = $data['minimum_awt_version'] ?? null;
        $maximum = $data['maximum_awt_version'] ?? null;
        if (!$minimum || version_compare(AWT_VERSION, $minimum, '<')
            || ($maximum && version_compare(AWT_VERSION, $maximum, '>'))) {
            throw new RuntimeException($data['name'] . ' is incompatible with the running AWT version.');
        }
    }

    private function canonical(array $manifest): array
    {
        if (!array_is_list($manifest)) ksort($manifest);
        foreach ($manifest as &$value) {
            if (is_array($value)) $value = $this->canonical($value);
        }
        return $manifest;
    }
}
