<?php

use router\Router;
use runtime\api\RuntimeRouterAPI;

final class PackageManagerRouter extends RuntimeRouterAPI
{
    public function main(): void
    {
        $controller = $this->getPassable('PackageManager', 'PackageManagerControllerAPI')->getController('PackageManagerController');
        foreach ([
            ['', 'index', 'GET'], ['/browse', 'browse', 'GET'], ['/help', 'help', 'GET'],
            ['/install-from-disk', 'installFromDisk', 'GET'],
            ['/install/review', 'reviewInstallation', 'POST'], ['/install/dependencies', 'prepareDependencies', 'POST'],
            ['/install/cancel', 'cancelInstallation', 'POST'], ['/logs', 'logs', 'GET'], ['/install', 'install', 'POST'], ['/action', 'action', 'POST'],
            ['/{package_id}', 'show', 'GET'],
        ] as [$suffix, $action, $method]) {
            $this->addRouter((new Router('/dashboard/packages' . $suffix, $action, clone $controller))->setMethod($method));
        }
    }
}
