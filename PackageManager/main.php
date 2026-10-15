<?php

use Dashboard\api\events\NavigationResponseEvent;
use Dashboard\api\events\WidgetRegistrationListener;
use Dashboard\api\events\WidgetRegistryEvent;
use Dashboard\api\navigation\NavigationItem;
use Dashboard\api\widget\DashboardWidget;
use Dashboard\api\widget\WidgetDefinition;
use object\ObjectFactory;
use PackageManager\widgets\RecentPackageActions;
use runtime\api\RuntimeLinkerAPI;
use runtime\enums\ERuntimeFlags;

final class PackageManager extends RuntimeLinkerAPI
{
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        $this->setRuntimeFlag(ERuntimeFlags::EventDispatcher);
    }

    public function main(): void
    {
        $this->createLink('PackageManagerControllerAPI', '/runtime/PackageManagerControllerAPI.php');
        $this->createLink('PackageManagerRouter', '/runtime/PackageManagerRouter.php');
        $nav = new NavigationItem('Packages', '/dashboard/packages', '/awt_packages/PackageManager/data/icon/icon.webp', permissionLevel: 0);
        foreach (['Installed' => '', 'Browse' => '/browse', 'Install from disk' => '/install-from-disk', 'Logs' => '/logs', 'Help' => '/help'] as $label => $suffix) {
            $nav->addChild(new NavigationItem($label, '/dashboard/packages' . $suffix, permissionLevel: 0));
        }
        $this->eventDispatcher->addListener(NavigationResponseEvent::NAME, new NavigationResponseEvent($nav));
        $definition = new WidgetDefinition(
            key: 'PackageManager/recent-actions', packageName: 'PackageManager',
            label: 'Recent package actions', description: 'Administrator package activity.',
            factory: (new ObjectFactory())->setClassName(RecentPackageActions::class)->setType(DashboardWidget::class),
            defaultVisible: true, defaultPosition: 40, size: DashboardWidget::SIZE_MEDIUM, permissionLevel: 0,
        );
        $this->eventDispatcher->addListener(WidgetRegistryEvent::NAME, new WidgetRegistrationListener($definition));
    }
}
