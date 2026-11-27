<?php

namespace PackageManager\service;

use admin\Admin;
use installer\framework\FrameworkUpdater;
use Throwable;

final class FrameworkUpdates
{
    public function __construct(private readonly ?FrameworkUpdater $updater = null) {}

    public function run(?Admin $actor = null): string
    {
        $log = $actor === null ? null : new ActionLog();
        $audit = $log?->begin($actor, 'AWT', 'update');
        try {
            $updater = $this->updater ?? new FrameworkUpdater(ROOT, FRAMEWORK_STORAGE . 'framework-update.lock');
            $message = $updater->run();
        } catch (Throwable $error) {
            $log?->finish($audit, 'failed', $error->getMessage());
            throw $error;
        }
        $log?->finish($audit, 'success', $message);
        return $message;
    }
}
