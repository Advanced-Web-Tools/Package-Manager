<?php

use installer\interfaces\package\actions\IPostUpdate;
use PackageManager\migrations\CreatePackageLog;

final class PackageManagerUpdate implements IPostUpdate
{
    public function postUpdate(int $packageId, string $packageName): void
    {
        CreatePackageLog::up($packageId);
    }
}
