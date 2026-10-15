<?php

namespace PackageManager\service;

use admin\Admin;
use database\DatabaseManager;
use database\provider\DatabaseProvider;
use PDO;

class ActionLog
{
    public function begin(Admin $actor, string $package, string $action): int
    {
        $name = Access::actor($actor);
        $id = (new DatabaseManager())->table('packagemanager_log')->insert([
            'account_id' => $actor->getKey(), 'account_name' => $name,
            'package_name' => $package, 'action' => $action, 'outcome' => 'pending',
            'message' => 'Action started.', 'created_at' => gmdate('Y-m-d H:i:s'),
        ])->executeInsert();
        if (!$id) throw new \RuntimeException('Unable to record this action. No change was started.');
        return $id;
    }

    public function finish(int $id, string $outcome, string $message, ?string $packageName = null): void
    {
        $values = ['outcome' => $outcome, 'message' => $message];
        if ($packageName !== null) $values['package_name'] = $packageName;
        if (!(new DatabaseManager())->table('packagemanager_log')->where(['id' => $id])->update($values)) throw new \RuntimeException('Action ran, but its log outcome could not be saved. Review installed packages before retrying.');
    }

    public function history(Admin $actor, int $page = 1, int $limit = 26): array
    {
        Access::administrator($actor);
        if ($page < 1 || $page > 1000000 || !in_array($limit, [5, 26], true)) throw new \InvalidArgumentException('Invalid log page.');
        $statement = (new DatabaseProvider())->execute(
            'SELECT account_name, package_name, action, outcome, message, created_at FROM packagemanager_log ORDER BY id DESC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => ($page - 1) * 25],
        );
        try { return $statement->fetchAll(PDO::FETCH_ASSOC); }
        finally { $statement->closeCursor(); }
    }
}
