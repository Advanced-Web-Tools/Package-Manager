<?php

namespace PackageManager\migrations;

use database\creator\ColumnCreator as C;
use database\creator\TableWizard;

final class CreateMarketplaceSources
{
    public static function up(int $packageId): void
    {
        $wizard = TableWizard::create($packageId);
        if ($wizard->tableExists('packagemanager_marketplace')) return;
        if (!$wizard
            ->addColumn(C::INT('package_id')->primary()->foreignKey('awt_package', 'id', onDelete: 'CASCADE'))
            ->addColumn(C::INT('storefront_id'))
            ->addColumn(C::INT('release_id'))
            ->addColumn(C::TEXT('latest_url'))
            ->addColumn(C::TEXT('download_url'))
            ->createTable('packagemanager_marketplace')) {
            throw new \RuntimeException('Could not create the Marketplace source table.');
        }
    }
}
