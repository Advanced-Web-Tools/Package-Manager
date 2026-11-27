<?php

namespace PackageManager\service;

use database\provider\DatabaseProvider;
use package\model\repository\PackageRepository;
use PDO;
use RuntimeException;

final class MarketplacePackageRepository extends PackageRepository
{
    public function __construct(private readonly array $source)
    {
        parent::__construct();
    }

    public function newPackage(array $data): ?int
    {
        return (new DatabaseProvider())->transaction(function () use ($data): int {
            $id = parent::newPackage($data);
            if (!$id) throw new RuntimeException('Could not register the Marketplace package.');
            $this->saveSource($id);
            return $id;
        });
    }

    public function updatePackage(int $id, array $data): bool
    {
        return (new DatabaseProvider())->transaction(function () use ($id, $data): bool {
            if (!parent::updatePackage($id, $data)) return false;
            $this->saveSource($id);
            return true;
        });
    }

    private function saveSource(int $id): void
    {
        if (!self::find($id)) {
            $this->insertSource($id);
            return;
        }
        (new DatabaseProvider())->execute(
            'UPDATE packagemanager_marketplace SET storefront_id = :storefront_id, release_id = :release_id,
                latest_url = :latest_url, download_url = :download_url WHERE package_id = :package_id',
            ['package_id' => $id] + $this->source,
        )->closeCursor();
    }

    public function insertSource(int $id): void
    {
        (new DatabaseProvider())->execute(
            'INSERT INTO packagemanager_marketplace (package_id, storefront_id, release_id, latest_url, download_url)
                VALUES (:package_id, :storefront_id, :release_id, :latest_url, :download_url)',
            ['package_id' => $id] + $this->source,
        )->closeCursor();
    }

    public static function find(int $id): ?array
    {
        $statement = (new DatabaseProvider())->execute(
            'SELECT * FROM packagemanager_marketplace WHERE package_id = :id', [':id' => $id],
        );
        try { return $statement->fetch(PDO::FETCH_ASSOC) ?: null; }
        finally { $statement->closeCursor(); }
    }
}
