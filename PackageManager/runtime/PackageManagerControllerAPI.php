<?php

use controller\Controller;
use object\ObjectFactory;
use runtime\api\RuntimeControllerAPI;
use runtime\enums\ERuntimeFlags;

final class PackageManagerControllerAPI extends RuntimeControllerAPI
{
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        $this->setRuntimeFlag(ERuntimeFlags::EventDispatcher);
    }

    public function main(): void
    {
        $factory = (new ObjectFactory())
            ->setType(Controller::class)
            ->setClassPath($this->runtimePath . '/controllers/PackageManagerController.php')
            ->setProperties([
                'controllerName' => 'PackageManagerController',
                'localAssetPath' => 'awt_packages/PackageManager/views/assets',
                'eventDispatcher' => $this->eventDispatcher,
            ])
            ->addMethodCall('setRootPath')->addMethodArgs('setRootPath', [$this->runtimePath])
            ->addMethodCall('setViewPath')->addMethodArgs('setViewPath', ['/views/']);
        $this->addController($factory, 'PackageManagerController');
    }
}
