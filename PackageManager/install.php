<?php

use installer\interfaces\package\actions\IPostInstall;
use PackageManager\migrations\CreatePackageLog;

final class PackageManagerInstall implements IPostInstall
{
    public function postInstall(int $packageId, string $packageName): bool
    {
        CreatePackageLog::up($packageId);
        return true;
    }
}
