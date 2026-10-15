<?php

namespace PackageManager\migrations;

use database\creator\ColumnCreator as C;
use database\creator\TableWizard;

final class CreatePackageLog
{
    public static function up(int $packageId, ?TableWizard $wizard = null): void
    {
        $wizard ??= TableWizard::create($packageId);
        if ($wizard->tableExists('packagemanager_log')) return;
        if (!$wizard
            ->addColumn(C::INT('id')->primary()->autoIncrement())
            ->addColumn(C::INT('account_id')->nullable()->index()->foreignKey('awt_admin', 'id', onDelete: 'SET NULL'))
            ->addColumn(C::VARCHAR('account_name', '255'))
            ->addColumn(C::VARCHAR('package_name', '255'))
            ->addColumn(C::VARCHAR('action', '32'))
            ->addColumn(C::VARCHAR('outcome', '32'))
            ->addColumn(C::TEXT('message'))
            ->addColumn(C::DATETIME('created_at')->index())
            ->createTable('packagemanager_log')) {
            throw new \RuntimeException('Could not create the package action log.');
        }
    }
}
