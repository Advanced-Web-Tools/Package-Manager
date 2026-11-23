<?php

use installer\interfaces\package\actions\IPostInstall;
use PackageManager\migrations\CreatePackageLog;
use PackageManager\migrations\CreateMarketplaceSources;

final class PackageManagerInstall implements IPostInstall
{
    public function postInstall(int $packageId, string $packageName): bool
    {
        CreatePackageLog::up($packageId);
        CreateMarketplaceSources::up($packageId);
        return true;
    }
}
