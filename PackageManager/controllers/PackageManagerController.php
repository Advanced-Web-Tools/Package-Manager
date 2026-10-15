<?php

use admin\Admin;
use Dashboard\api\page\DashboardControllerBase;
use PackageManager\service\Access;
use PackageManager\service\ActionLog;
use PackageManager\service\PackageArchive;
use PackageManager\service\Packages;
use PackageManager\service\PackageReadme;
use PackageManager\service\InstallationPlan;
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
        return $this->page('browse', 'Browse');
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
            return $this->page('show', 'Installed', compact('package', 'metadata', 'description', 'dependencies', 'readme', 'readmeError', 'packageTab'));
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
