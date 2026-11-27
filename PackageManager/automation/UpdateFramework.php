<?php

namespace PackageManager\automation;

use automation\interfaces\AutomationJob;
use PackageManager\service\FrameworkUpdates;
use PackageManager\service\UpdateSettings;

final class UpdateFramework implements AutomationJob
{
    public function name(): string
    {
        return 'update-framework';
    }

    public function on(): string
    {
        return '0 */2 * * *';
    }

    public function run(): void
    {
        if (UpdateSettings::enabled(UpdateSettings::FRAMEWORK)) {
            $message = (new FrameworkUpdates())->run();
            fwrite(STDOUT, $message . PHP_EOL);
        }
    }
}
