<?php

namespace PackageManager\widgets;

use admin\Admin;
use Dashboard\api\widget\DashboardWidget;
use PackageManager\service\ActionLog;
use PackageManager\service\Access;
use URPC\api\URPCBlock;
use URPC\api\URPCBlockDescriptor;

final class RecentPackageActions extends DashboardWidget
{
    public function __construct(private readonly ActionLog $log = new ActionLog())
    {
        parent::__construct();
    }

    public function widget(): DashboardWidget
    {
        $account = new Admin();
        Access::administrator($account);

        $descriptor = new URPCBlockDescriptor('section');
        $descriptor->setSourceKey('PackageManager/widgets/recent-actions');
        $this->setBlockDescriptor($descriptor);
        $this->setBlockAttributes(['class' => 'card min-w-0']);

        $children = [
            $this->text('h2', 'Recent package actions', ['class' => 'card-title']),
            $this->text('p', 'The latest five package actions.', ['class' => 'card-subtitle text-small mb-3']),
        ];
        $changes = $this->log->history($account, 1, 5);
        if ($changes === []) {
            $children[] = $this->text('p', 'No package actions recorded yet.', ['class' => 'text-small text-muted mb-0']);
        } else {
            $list = new URPCBlock(new URPCBlockDescriptor('ul'));
            $list->setBlockAttributes(['class' => 'list-group', 'aria-label' => 'Package actions, newest first']);
            foreach ($changes as $change) {
                $date = new \DateTimeImmutable($change['created_at'], new \DateTimeZone('UTC'));
                $item = new URPCBlock(new URPCBlockDescriptor('li'));
                $item->setBlockAttributes(['class' => 'd-block']);
                $item->setBlockChildren([
                    $this->text('p', $change['account_name'] . ' · ' . $change['action'] . ' · ' . $change['package_name'] . ' · ' . $change['outcome'], ['class' => 'text-small mb-1']),
                    $this->text('time', $date->format('j M Y, H:i \U\T\C'), [
                        'class' => 'd-block text-small text-muted',
                        'datetime' => $date->format(DATE_ATOM),
                    ]),
                ]);
                $list->addBlockChild($item);
            }
            $children[] = $list;
        }
        $children[] = $this->text('a', 'Open packages', ['class' => 'btn btn-outline-secondary btn-sm mt-3', 'href' => '/dashboard/packages']);
        $children[] = $this->text('a', 'View all logs', ['class' => 'btn btn-ghost btn-sm mt-3', 'href' => '/dashboard/packages/logs']);
        $this->setBlockChildren($children);
        return $this;
    }

    private function text(string $tag, string $content, array $attributes): URPCBlock
    {
        $block = new URPCBlock(new URPCBlockDescriptor($tag));
        $block->setBlockAttributes($attributes);
        $block->setBlockContent($content);
        return $block;
    }
}
