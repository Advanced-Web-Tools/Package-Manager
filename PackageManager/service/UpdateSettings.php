<?php

namespace PackageManager\service;

use setting\Config;
use setting\Settings;

final class UpdateSettings
{
    public const PACKAGES = 'Auto update packages';
    public const FRAMEWORK = 'Auto update framework';

    public static function register(int $packageId): void
    {
        $settings = new Settings();
        foreach ([self::PACKAGES, self::FRAMEWORK] as $name) {
            $settings->createSetting($packageId, $name, 'false', 0, 'boolean', 'Package Manager');
        }
    }

    public static function enabled(string $name): bool
    {
        return Config::getConfig('PackageManager', $name)?->getValue() === 'true';
    }
}
