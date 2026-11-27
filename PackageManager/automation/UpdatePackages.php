<?php

namespace PackageManager\automation;

use automation\interfaces\AutomationJob;
use PackageManager\service\PackageUpdates;
use PackageManager\service\UpdateSettings;
use RuntimeException;

final class UpdatePackages implements AutomationJob
{
    public function name(): string
    {
        return 'update-packages';
    }

    public function on(): string
    {
        return '0 */2 * * *';
    }

    public function run(): void
    {
        if (!UpdateSettings::enabled(UpdateSettings::PACKAGES)) return;

        $failures = (new PackageUpdates())->updateAll()['failures'];
        if ($failures) throw new RuntimeException(implode(PHP_EOL, $failures));
    }
}
