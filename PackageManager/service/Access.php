<?php

namespace PackageManager\service;

use admin\Admin;

final class Access
{
    public static function administrator(Admin $account): void
    {
        if (!$account->checkAuthentication() || $account->permission_level === null || !$account->checkPermission(0)) {
            throw new \LogicException('Administrator permission 0 is required.');
        }
    }

    public static function actor(Admin $account): string
    {
        self::administrator($account);
        return (string)$account->username;
    }
}
