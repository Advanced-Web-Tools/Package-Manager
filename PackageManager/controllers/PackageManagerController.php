<?php

use admin\Admin;
use Dashboard\api\page\DashboardControllerBase;
use PackageManager\service\Access;
use PackageManager\service\ActionLog;
use PackageManager\service\FrameworkUpdates;
use PackageManager\service\PackageArchive;
use PackageManager\service\Packages;
use PackageManager\service\PackageReadme;
use PackageManager\service\PackageUpdates;
use PackageManager\service\InstallationPlan;
use PackageManager\service\Marketplace;
use PackageManager\service\MarketplaceInstallation;
use PackageManager\service\MarketplacePackageRepository;
use redirect\Redirect;
use response\Response;
use router\security\Csrf;
use view\View;

final class PackageManagerController extends DashboardControllerBase
{
    // The framework exposes view bundles as controller properties during rendering.
    public string $navigation;
    public string $name;
    public string $tab;
    public string $packageTab;
    public string $token;
    public ?array $notice;
    public ?array $enablePrompt;
    public ?array $installation;
    public array $packages;
    public array $packageTypes;
    public string $search;
    public string $typeFilter;
    public int $totalPackages;
    public ?array $readme;
    public ?string $readmeError;
    public ?\package\model\InstalledPackage $package;
    public array $metadata;
    public array $dependencies;
    public ?string $description;
    public array $entries;
    public int $logPage;
    public bool $hasMore;
    public array $marketplaceBrowse;
    public ?array $marketplaceSource;
    public ?string $marketplaceUpdate;
    public ?string $marketplaceUpdateError;
    public array $updateFailures;

    public function __construct()
    {
        $this->checkAuth();
        $this->authorize();
    }

    private function authorize(): Admin
    {
        $actor = new Admin();
        try { Access::administrator($actor); }
        catch (LogicException) {
            Response::make(403, 'Administrator permission 0 is required.')->send();
            exit;
        }
        return $actor;
    }

    public function index(array|string $params): View
    {
        $actor = $this->authorize();
        try {
            $catalog = (new Packages())->catalog($actor,
                is_string($_GET['q'] ?? null) ? $_GET['q'] : '',
                is_string($_GET['type'] ?? null) ? $_GET['type'] : '',
            );
        } catch (InvalidArgumentException $error) {
            $catalog = (new Packages())->catalog($actor);
            $_SESSION['packagemanager_notice'] = ['kind' => 'error', 'message' => $error->getMessage()];
        }
        return $this->page('index', 'Installed', $catalog);
    }

    public function browse(array|string $params): View
    {
        $actor = $this->authorize();
        $labels = Marketplace::SECTIONS;
        $sorts = ['name' => 'Name'] + $labels;
        $section = is_string($_GET['section'] ?? null) && isset($labels[$_GET['section']]) ? $_GET['section'] : '';
        $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $sort = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'name';
        $tag = filter_var($_GET['tag'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: '';
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $detailId = filter_var($_GET['package'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $profileType = in_array($_GET['owner'] ?? null, ['creator', 'organization'], true) ? $_GET['owner'] : null;
        $profileId = filter_var($_GET['profile'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $profile = $detail = $error = $previous = $next = null;
        $sections = $tags = [];
        try {
            if (!preg_match('/\A.{0,200}\z/us', $search)) throw new InvalidArgumentException('Search must contain at most 200 characters of valid text.');
            $market = new Marketplace();
            $compatibility = Marketplace::compatibility();
            $installed = [];
            foreach ((new Packages())->all($actor) as $package) $installed[$package->name] = $package;
            $markup = new PackageReadme();
            $decorate = static function (array $item) use ($market, $installed, $markup): array {
                $item['description_excerpt'] = $markup->description($item['description_html'] ?? $item['description'] ?? null, marketplace: $market, excerpt: true);
                $item['release'] = $item['latest_compatible_stable'] ?? null;
                if (!array_key_exists('latest_compatible_stable', $item)) $item['release'] = $item['latest_stable'] ?? null;
                $item['installed_id'] = ($installed[$item['release']['name'] ?? $item['latest_stable']['name'] ?? ''] ?? null)?->id;
                return $item + $market->artwork($item);
            };

            if ($detailId) {
                $detail = $this->marketplaceDetail($market, $decorate($market->get('/package/' . $detailId, $compatibility)), $installed, $page);
            } elseif ($profileType && $profileId) {
                $path = $profileType === 'creator' ? '/creators/' : '/organizations/';
                $path .= $profileId;
                $profile = $market->get($path);
                $profile += $market->artwork($profile);
                $profile['type'] = $profileType;
                $profile['description_markup'] = $markup->description($profile['description_html'] ?? $profile['description'] ?? null, marketplace: $market);
                if ($profileType === 'creator') {
                    $profile['avatar'] = $market->creatorAvatar($profile);
                }
                $profile['gallery'] = $market->gallery($profile['gallery'] ?? null);
                $profile['relationships'] = $market->profileRelationships($profile, $profileType);
                $profile['links'] = array_values(array_filter(array_map(static function (array $link) use ($market): ?array {
                    $url = $market->media($link['url']);
                    return $url ? ['label' => $link['label'], 'url' => $url] : null;
                }, $profile['links'])));
                $collection = $market->get($path . '/storefronts', $compatibility + ['page' => $page]);
                $profile['items'] = array_map($decorate, $collection['items']);
                $profileUrl = static fn(int $number): string => '/dashboard/packages/browse?' . http_build_query([
                    'owner' => $profileType, 'profile' => $profileId, 'page' => $number,
                ]);
                $previous = $page > 1 ? $profileUrl($page - 1) : null;
                $next = $page < ceil($collection['total'] / $collection['per_page']) ? $profileUrl($page + 1) : null;
            } else {
                $tags = $market->get('/tags')['items'];
                $filters = ['q' => $search, 'tag' => $tag, 'sort' => $sort];
                if (!$section && $search === '' && $tag === '' && !isset($_GET['sort'])) {
                    foreach ($market->get('/browse/home', $compatibility)['sections'] as $key => $items) {
                        if ($items && isset($labels[$key])) $sections[$key] = array_map($decorate, $items);
                    }
                } else {
                    $query = $section ? ['section' => $section] : $filters;
                    $path = $section ? '/browse/' . $section : '/package/search';
                    $parameters = $section ? [] : array_filter($filters, static fn($value) => $value !== '');
                    $data = $market->get($path, $parameters + $compatibility + ['page' => $page]);
                    $sections[$section ?: 'search'] = array_map($decorate, $data['items']);
                    $pageUrl = static fn(int $number): string => '/dashboard/packages/browse?' . http_build_query($query + ['page' => $number]);
                    if ($page > 1) $previous = $pageUrl($page - 1);
                    if ($page < ceil($data['total'] / $data['per_page'])) $next = $pageUrl($page + 1);
                }
            }
        } catch (Throwable $failure) {
            $profile = null;
            $error = $failure->getMessage();
        }
        $marketplaceBrowse = compact('sections', 'labels', 'sorts', 'sort', 'section', 'tags', 'tag', 'search', 'detail', 'profile', 'error', 'page', 'previous', 'next');
        return $this->page('browse', 'Browse', compact('marketplaceBrowse'));
    }

    private function marketplaceDetail(Marketplace $market, array $item, array $installed, int $page): array
    {
        $item['description_markup'] = (new PackageReadme())->description($item['description_html'] ?? $item['description'] ?? null, marketplace: $market);
        $item['gallery'] = $market->gallery($item['gallery'] ?? null);
        $id = $item['id'];
        $base = '/dashboard/packages/browse?package=' . $id;
        $data = $market->get("/package/{$id}/versions", Marketplace::compatibility() + ['page' => $page]);
        $requested = filter_var($_GET['release'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $versions = array_column($data['items'], null, 'id');
        $selected = $versions[$requested] ?? $versions[$item['release']['id'] ?? 0] ?? reset($versions);
        $item['selected'] = $selected ?: null;
        $item['versions'] = $versions;
        $item['page'] = $page;
        $item['previous'] = $page > 1 ? $base . '&page=' . ($page - 1) : null;
        $item['next'] = $page < ceil($data['total'] / $data['per_page']) ? $base . '&page=' . ($page + 1) : null;
        $item['rating_url'] = $market->origin . '/storefront/' . $id . '#reviews';
        $owner = $item['owner'];
        $item['owner_url'] = '/dashboard/packages/browse?' . http_build_query(['owner' => $owner['type'], 'profile' => $owner['id']]);
        $item['publisher'] = ucfirst($owner['type']);
        try {
            $collection = $owner['type'] === 'creator' ? 'creators' : 'organizations';
            $publisher = $market->get("/{$collection}/" . (int) $owner['id']);
            $item['publisher'] = $publisher['name'] ?? $item['publisher'];
        } catch (RuntimeException | JsonException $error) {
            // A publisher profile can become unavailable independently of its package.
        }
        $item['manifest'] = [];
        $item['operation'] = null;
        $item['can_connect'] = false;
        $item['installed'] = $installed[$selected['name'] ?? $item['release']['name'] ?? $item['latest_stable']['name'] ?? ''] ?? null;
        if (!$selected) return $item;

        $release = $market->get("/package/manifest/{$id}", ['version_id' => $selected['id']]);
        $manifest = $item['manifest'] = $release['manifest'];
        $current = $item['installed'];
        if (($manifest['type'] ?? 0) === 0) return $item;
        if (!$current) {
            $item['operation'] = 'Install on server';
            return $item;
        }
        $source = MarketplacePackageRepository::find($current->id);
        if ($manifest['type'] !== $current->type || $current->system_package !== !empty($manifest['system_package']) || ($source && $source['latest_url'] !== $market->source($id, $selected['id'])['latest_url'])) return $item;
        $item['can_connect'] = $source === null && $manifest['name'] === $current->name;
        $latest = $item['release'];
        if ($latest && $latest['name'] === $current->name && version_compare($latest['version'], $current->version, '>')) {
            $item['update_url'] = $base . '#pm-release-action';
        }
        $item['operation'] = match (version_compare($selected['version'], $current->version)) {
            1 => 'Update', -1 => 'Downgrade', default => null,
        };
        if ($item['operation'] === 'Update') $item['update_url'] = '#pm-release-action';
        return $item;
    }

    public function marketplacePreview(array|string $params): Response
    {
        $this->authorize();
        try {
            $id = $this->id(is_array($params) ? ($params['storefront_id'] ?? null) : null);
            $market = new Marketplace();
            $detail = $market->get("/package/{$id}");
            return Response::make(200)->asJson()->data(['video' => $market->artwork($detail)['video']])
                ->header('Cache-Control', 'private, max-age=300');
        } catch (InvalidArgumentException) {
            return Response::make(400, 'Invalid storefront ID')->asJson();
        } catch (Throwable $error) {
            return Response::make($error->getCode() === 404 ? 404 : 502, 'Preview unavailable')->asJson();
        }
    }

    public function installFromMarketplace(array|string $params): Redirect
    {
        $id = filter_var($_POST['storefront_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $versionId = filter_var($_POST['release_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $releasePage = filter_var($_POST['release_page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $destination = '/dashboard/packages/browse';
        if ($id) $destination .= '?' . http_build_query(['package' => $id, 'release' => $versionId, 'page' => $releasePage]);
        try {
            $installedId = isset($_POST['installed_id']) ? $this->id($_POST['installed_id']) : null;
            $installedVersion = is_string($_POST['installed_version'] ?? null) ? $_POST['installed_version'] : null;
            $package = (new MarketplaceInstallation())->install(
                $this->authorize(), $this->id($id), $this->id($versionId), ($_POST['confirmed'] ?? '') === '1',
                $installedId, $installedVersion, ($_POST['downgrade_confirmed'] ?? '') === '1',
            );
            if ($installedId !== null) {
                return $this->redirect($package->name . ' switched to version ' . $package->version . '.', 'success', $destination);
            }
            if ($package->status) return $this->redirect($package->name . ' installed and enabled.', 'success');
            $_SESSION['packagemanager_enable'] = ['id' => $package->id, 'name' => $package->name];
            return $this->redirect($package->name . ' installed successfully. Would you like to enable it?', 'success');
        } catch (Throwable $error) {
            return $this->redirect('Marketplace package action failed: ' . $error->getMessage(), 'error', $destination);
        }
    }

    public function connectMarketplace(array|string $params): Redirect
    {
        $actor = $this->authorize();
        $destination = '/dashboard/packages/browse';
        try {
            $storefrontId = $this->id($_POST['storefront_id'] ?? null);
            $releaseId = $this->id($_POST['release_id'] ?? null);
            $destination .= '?' . http_build_query(['package' => $storefrontId, 'release' => $releaseId]);
            $installedId = $this->id($_POST['installed_id'] ?? null);
            $installedVersion = $_POST['installed_version'] ?? null;
            if (!is_string($installedVersion)) throw new InvalidArgumentException('Review the installed package before connecting it.');
            (new MarketplaceInstallation())->connect($actor, $installedId, $installedVersion, $storefrontId, $releaseId);
            return $this->redirect('Installed package connected to the Marketplace storefront.', 'success', '/dashboard/packages/' . $installedId);
        } catch (Throwable $error) {
            return $this->redirect($error->getMessage(), 'error', $destination);
        }
    }

    public function installFromDisk(array|string $params): View
    {
        try { $installation = (new InstallationPlan())->review($this->authorize()); }
        catch (Throwable $error) {
            $installation = null;
            $_SESSION['packagemanager_notice'] = ['kind' => 'error', 'message' => $error->getMessage()];
        }
        return $this->page('install', 'Install from disk', compact('installation'));
    }

    public function help(array|string $params): View
    {
        return $this->page('help', 'Help');
    }

    public function show(array|string $params): View|Redirect
    {
        $actor = $this->authorize();
        try {
            $id = $this->id(is_array($params) ? ($params['package_id'] ?? null) : null);
            $service = new Packages();
            $package = $service->find($actor, $id);
            $metadata = $service->metadata($package);
            $description = $package->description;
            unset($metadata['description']);
            $dependencies = $service->dependencies($actor, $package);
            $readmeError = null;
            try { $readme = (new PackageReadme())->load($package); }
            catch (RuntimeException $error) { $readme = null; $readmeError = $error->getMessage(); }
            $availableTabs = ['overview'];
            if ($readme) $availableTabs[] = 'readme';
            if ($package->license || Packages::licenseUrl($package->license_url)) $availableTabs[] = 'license';
            $packageTab = in_array($_GET['tab'] ?? null, $availableTabs, true) ? $_GET['tab'] : 'overview';
            $marketplaceSource = MarketplacePackageRepository::find($id);
            $marketplaceUpdate = $marketplaceUpdateError = null;
            if ($marketplaceSource && ($_GET['check_update'] ?? null) === '1') {
                try {
                    $latest = Marketplace::fromSource($marketplaceSource)->latestForPackage((int) $marketplaceSource['storefront_id'], $package->name);
                    $marketplaceUpdate = version_compare($latest['version'], $package->version, '>')
                        ? 'Version ' . $latest['version'] . ' is available.' : 'You have the latest compatible stable version.';
                } catch (Throwable $error) {
                    $marketplaceUpdateError = $error->getCode() === 404
                        ? 'No compatible stable release is currently available.' : $error->getMessage();
                }
            }
            return $this->page('show', 'Installed', compact('package', 'metadata', 'description', 'dependencies', 'readme',
                'readmeError', 'packageTab', 'marketplaceSource', 'marketplaceUpdate', 'marketplaceUpdateError'));
        } catch (Throwable $error) {
            return $this->redirect($error->getMessage(), 'error');
        }
    }

    public function logs(array|string $params): View
    {
        $actor = $this->authorize();
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
        $entries = (new ActionLog())->history($actor, $page);
        return $this->page('logs', 'Logs', ['entries' => array_slice($entries, 0, 25), 'logPage' => $page, 'hasMore' => count($entries) > 25]);
    }

    public function updates(array|string $params): View
    {
        $updateFailures = $_SESSION['packagemanager_update_failures'] ?? [];
        unset($_SESSION['packagemanager_update_failures']);
        return $this->page('updates', 'Updates', compact('updateFailures'));
    }

    public function runUpdates(array|string $params): Redirect
    {
        $actor = $this->authorize();
        $destination = '/dashboard/packages/updates';
        unset($_SESSION['packagemanager_update_failures']);
        try {
            $operation = $_POST['operation'] ?? null;
            if ($operation === 'update-framework') {
                return $this->redirect((new FrameworkUpdates())->run($actor), 'success', $destination);
            }
            $updates = new PackageUpdates();
            [$result, $verb] = match ($operation) {
                'connect-all' => [$updates->connectAll($actor), 'connected'],
                'update-all' => [$updates->updateAll($actor), 'updated'],
                default => throw new InvalidArgumentException('Choose a valid update action.'),
            };
            $failed = count($result['failures']);
            $_SESSION['packagemanager_update_failures'] = $result['failures'];
            $message = sprintf('%d %s, %d unchanged, %d need attention.', $result['changed'], $verb, $result['skipped'], $failed);
            return $this->redirect($message, $failed ? 'error' : 'success', $destination);
        } catch (Throwable $error) {
            return $this->redirect($error->getMessage(), 'error', $destination);
        }
    }

    public function action(array|string $params): Redirect
    {
        $actor = $this->authorize();
        $destination = '/dashboard/packages';
        try {
            $id = $this->id($_POST['package_id'] ?? null);
            $action = $_POST['operation'] ?? null;
            if (!is_string($action) || !in_array($action, ['enable', 'disable', 'remove'], true)) throw new InvalidArgumentException('Choose a valid package action.');
            $returnTab = $_POST['return_tab'] ?? null;
            if (in_array($action, ['enable', 'disable'], true) && in_array($returnTab, ['overview', 'readme', 'license'], true)) {
                $destination .= '/' . $id . ($returnTab === 'overview' ? '' : '?tab=' . $returnTab);
            }
            if ($action === 'remove' && ($_POST['confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Confirm package removal.');
            $message = (new Packages())->change($actor, $id, $action);
            return $this->redirect($message, 'success', $destination);
        } catch (Throwable $error) {
            return $this->redirect($error->getMessage(), 'error', $destination);
        }
    }

    public function reviewInstallation(array|string $params): Redirect
    {
        $actor = $this->authorize();
        try {
            $upload = $_FILES['archive'] ?? null;
            if (!is_array($upload)) throw new InvalidArgumentException('Choose a ZIP archive. The request may exceed the server upload limit.');
            (new InstallationPlan())->stage($actor, (new PackageArchive())->uploadPath($upload));
            return (new Redirect())->redirect('/dashboard/packages/install-from-disk');
        } catch (Throwable $error) {
            return $this->redirect('Unable to review package: ' . $error->getMessage(), 'error', '/dashboard/packages/install-from-disk');
        }
    }

    public function prepareDependencies(array|string $params): Redirect
    {
        $actor = $this->authorize();
        try {
            (new InstallationPlan())->prepare($actor, $this->installationToken(),
                ($_POST['confirmed'] ?? '') === '1', ($_POST['skip_prompts'] ?? '') === '1');
            return (new Redirect())->redirect('/dashboard/packages/install-from-disk');
        } catch (Throwable $error) {
            return $this->redirect($error->getMessage(), 'error', '/dashboard/packages/install-from-disk');
        }
    }

    public function cancelInstallation(array|string $params): Redirect
    {
        try { (new InstallationPlan())->cancel($this->authorize(), $this->installationToken()); }
        catch (Throwable $error) { return $this->redirect($error->getMessage(), 'error', '/dashboard/packages/install-from-disk'); }
        return (new Redirect())->redirect('/dashboard/packages/install-from-disk');
    }

    public function install(array|string $params): Redirect
    {
        $actor = $this->authorize();
        try {
            $package = (new InstallationPlan())->install($actor, $this->installationToken(), ($_POST['confirmed'] ?? '') === '1');
            $_SESSION['packagemanager_enable'] = ['id' => $package->id, 'name' => $package->name];
            return $this->redirect($package->name . ' installed successfully. Would you like to enable it?', 'success');
        } catch (Throwable $error) {
            return $this->redirect('Installation failed: ' . $error->getMessage() . ' Any packages already installed have been retained.', 'error', '/dashboard/packages/install-from-disk');
        }
    }

    private function installationToken(): string
    {
        $token = $_POST['installation_token'] ?? null;
        if (!is_string($token)) throw new InvalidArgumentException('Choose a ZIP archive and review it before installing.');
        return $token;
    }

    private function id(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: throw new InvalidArgumentException('Choose a valid package ID.');
    }

    private function redirect(string $message, string $kind, string $destination = '/dashboard/packages'): Redirect
    {
        $_SESSION['packagemanager_notice'] = compact('message', 'kind');
        return (new Redirect())->redirect($destination);
    }

    private function page(string $view, string $tab, array $data = []): View
    {
        $this->authorize();
        $this->viewName = $view;
        $notice = $_SESSION['packagemanager_notice'] ?? null;
        $enablePrompt = $view === 'index' ? ($_SESSION['packagemanager_enable'] ?? null) : null;
        unset($_SESSION['packagemanager_notice']);
        if ($view === 'index') unset($_SESSION['packagemanager_enable']);
        return $this->view(array_merge([
            'navigation' => $this->buildNavigation(), 'name' => 'Packages', 'tab' => $tab,
            'token' => (new Csrf())->token(), 'notice' => $notice, 'enablePrompt' => $enablePrompt, 'package' => null,
        ], $data));
    }

    public function view(array $data = []): View
    {
        $this->bundle = $data;
        $this->createDocument();
        return $this;
    }
}
