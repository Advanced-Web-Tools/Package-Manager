<?php

use installer\interfaces\package\actions\IPostUpdate;
use PackageManager\migrations\CreatePackageLog;
use PackageManager\migrations\CreateMarketplaceSources;

final class PackageManagerUpdate implements IPostUpdate
{
    public function postUpdate(int $packageId, string $packageName): void
    {
        CreatePackageLog::up($packageId);
        CreateMarketplaceSources::up($packageId);
    }
}
