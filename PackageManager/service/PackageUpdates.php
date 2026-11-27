<?php

namespace PackageManager\service;

use admin\Admin;
use package\facade\PackageFacade;
use RuntimeException;
use Throwable;

final class PackageUpdates
{
    public function __construct(private readonly DependencyDownload $download = new DependencyDownload()) {}

    public function connectAll(Admin $actor): array
    {
        Access::administrator($actor);
        $changed = $skipped = 0;
        $failures = $unconnected = [];
        foreach ((new PackageFacade())->getInstalled() as $package) {
            if ($package->type === 0) continue;
            if (MarketplacePackageRepository::find($package->id) === null) {
                $unconnected[$package->name] = $package;
            } else {
                ++$skipped;
            }
        }
        if (!$unconnected) return compact('changed', 'skipped', 'failures');

        $market = new Marketplace(download: $this->download);
        $matches = $this->findStorefronts($market, $unconnected);
        $installer = new MarketplaceInstallation($market);
        foreach ($unconnected as $name => $package) {
            $releases = $matches[$name] ?? [];
            if (!$releases) {
                ++$skipped;
                continue;
            }
            if (count($releases) > 1) {
                $failures[] = $name . ' — Multiple storefronts match. Choose a storefront in Browse.';
                continue;
            }
            $id = array_key_first($releases);
            try {
                if ($installer->connect($actor, $package->id, $package->version, $id, $releases[$id], matchAuthor: true)) {
                    ++$changed;
                } else {
                    ++$skipped;
                }
            } catch (Throwable $error) {
                $failures[] = $name . ' — ' . $error->getMessage();
            }
        }
        return compact('changed', 'skipped', 'failures');
    }

    public function updateAll(?Admin $actor = null): array
    {
        if ($actor !== null) Access::administrator($actor);
        $changed = $skipped = 0;
        $failures = [];
        foreach ((new PackageFacade())->getInstalled() as $package) {
            if ($package->type === 0) continue;
            try {
                $source = MarketplacePackageRepository::find($package->id);
                if ($source === null) {
                    ++$skipped;
                    continue;
                }
                $market = Marketplace::fromSource($source, $this->download);
                $updated = (new MarketplaceInstallation($market))->update($package, (int) $source['storefront_id'], $actor);
                if ($updated === null) {
                    ++$skipped;
                } else {
                    ++$changed;
                }
            } catch (Throwable $error) {
                $failures[] = $package->name . ' — ' . $error->getMessage();
            }
        }
        return compact('changed', 'skipped', 'failures');
    }

    /** Find compatible stable releases by manifest name, independently of their storefront title. */
    private function findStorefronts(Marketplace $market, array $packages): array
    {
        $compatibility = Marketplace::compatibility();
        $releaseKey = $compatibility ? 'latest_compatible_stable' : 'latest_stable';
        $matches = [];
        for ($page = 1; ; ++$page) {
            $data = $market->get('/package/search', $compatibility + ['page' => $page]);
            $total = $data['total'] ?? null;
            $pageSize = $data['per_page'] ?? null;
            if (!is_array($data['items'] ?? null) || !is_int($total) || $total < 0 || !is_int($pageSize) || $pageSize < 1) {
                throw new RuntimeException('Marketplace returned an invalid storefront list.');
            }
            foreach ($data['items'] as $item) {
                $release = $item[$releaseKey] ?? null;
                $name = $release['name'] ?? null;
                $storefront = $item['id'] ?? null;
                $revision = $release['id'] ?? null;
                if (is_string($name) && isset($packages[$name]) && is_int($storefront) && $storefront > 0
                    && ($release['package_id'] ?? null) === $storefront && is_int($revision) && $revision > 0) {
                    $matches[$name][$storefront] = $revision;
                }
            }
            if ($page * $pageSize >= $total) return $matches;
        }
    }
}
