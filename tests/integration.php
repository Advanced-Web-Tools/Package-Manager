<?php
/** Run with AWT_FRAMEWORK_ROOT and AWT_DASHBOARD_ROOT pointing to local checkouts. */
declare(strict_types=1);

$framework = getenv('AWT_FRAMEWORK_ROOT') ?: dirname(__DIR__, 3) . '/Advanced-Web-Tools-Framework';
$dashboard = getenv('AWT_DASHBOARD_ROOT') ?: dirname(__DIR__, 2) . '/Dashboard/Dashboard';
if (!is_dir($framework . '/awt_src/classes') || !is_dir($dashboard . '/views')) {
    throw new RuntimeException('Set AWT_FRAMEWORK_ROOT and AWT_DASHBOARD_ROOT to framework and Dashboard checkouts.');
}
$temporary = sys_get_temp_dir() . '/packagemanager-tests-' . bin2hex(random_bytes(8));
mkdir($temporary);
define('ROOT', $temporary . '/');
define('CONFIG', ROOT);
define('DATA', ROOT . 'data/');
define('PACKAGES', ROOT . 'packages/');
define('PACKAGE_STORAGE', ROOT . 'package-storage/');
define('COMPILED', ROOT . 'compiled/');
define('HOSTNAME', 'http://localhost');
define('WEB_NAME', 'AWT test');
define('AWT_VERSION', '27.0.0');
define('DEBUG', true);
foreach ([DATA, PACKAGES, PACKAGE_STORAGE, COMPILED] as $path) mkdir($path, 0755, true);
file_put_contents(CONFIG . 'awt_db.php', "<?php const DB_TYPE = 'sqlite'; const DB_NAME = ':memory:';");
function removeTree(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $file) removeTree($path . '/' . $file);
        rmdir($path);
    } else { unlink($path); }
}
register_shutdown_function(static function () use ($temporary): void { session_write_close(); removeTree($temporary); });
spl_autoload_register(static function (string $class) use ($framework, $dashboard): void {
    $relative = str_replace('\\', '/', $class) . '.php';
    $path = match (true) {
        str_starts_with($class, 'PackageManager\\') => dirname(__DIR__) . '/' . $relative,
        str_starts_with($class, 'Dashboard\\') => dirname($dashboard) . '/' . $relative,
        str_starts_with($class, 'URPC\\') => dirname(__DIR__, 2) . '/URPC/' . $relative,
        default => $framework . '/awt_src/classes/' . $relative,
    };
    if (is_file($path)) require_once $path;
});
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) use ($framework) {
    if ($severity === E_DEPRECATED && str_starts_with($file, $framework)) return true;
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
session_save_path(ROOT);
session_start();
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects(callable $operation, string $message): void {
    try { $operation(); } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $message), 'Expected ' . $message . ', got ' . $error);
        return;
    }
    throw new RuntimeException('Expected failure: ' . $message);
}
$provider = new database\provider\DatabaseProvider();
$provider->execute('CREATE TABLE awt_table (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, creator INTEGER)');
$provider->execute('CREATE TABLE awt_table_structure (id INTEGER PRIMARY KEY AUTOINCREMENT, table_id INTEGER, column_name TEXT, column_type TEXT)');
$installer = new installer\framework\FrameworkInstallService();
foreach (['migratePackageTable', 'migrateAdmin', 'migrateStorageTable'] as $method) (new ReflectionMethod($installer, $method))->invoke($installer);
$provider->execute("INSERT INTO awt_admin (id, username, firstname, lastname, email, password, token, permission_level, profile_picture) VALUES (1, 'administrator', 'Admin', 'User', 'test@example.test', '', 'test-token', 0, NULL)");
$_SESSION['admin'] = ['id' => 1, 'token' => 'test-token'];
$actor = new admin\Admin();
PackageManager\service\Access::administrator($actor);
PackageManager\migrations\CreatePackageLog::up(1);
PackageManager\migrations\CreatePackageLog::up(1);
check(count($provider->execute("SELECT name FROM awt_table WHERE name='packagemanager_log'")->fetchAll()) === 1, 'Migration idempotent');
$facade = new package\facade\PackageFacade();
$service = new PackageManager\service\Packages();
$archive = new PackageManager\service\PackageArchive();
$base = ['name' => 'Example', 'version' => '1.0.0', 'author' => 'AWT', 'minimum_awt_version' => '27.0.0', 'type' => 1, 'system_package' => false, 'dependencies' => [], 'description' => '<script>alert("x")</script>', 'license' => 'MIT', 'license_url' => 'https://example.test/license', 'custom_field' => ['one' => 'two']];
function zipPackage(string $path, array $manifest, array $extra = []): void {
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode($manifest));
    $zip->addFromString('main.php', '<?php // test package');
    foreach ($extra as $name => $value) $zip->addFromString($name, $value);
    $zip->close();
}
$path = ROOT . 'example.zip';
zipPackage($path, $base);
check($archive->inspect($path)['name'] === 'Example', 'Valid ZIP inspection');
rejects(fn() => $service->install($actor, $path, false), 'Confirm');
$package = $service->install($actor, $path, true);
check(!$package->status && is_file(PACKAGES . 'Example/main.php'), 'Framework installation starts disabled');
check(glob(DATA . 'storage/framework/temp/PackageManager/*.archive') === [], 'Transient upload cleaned');
check($service->metadata($package)['author'] === 'AWT', 'Metadata comes from database model');
check(!array_key_exists('custom_field', $service->metadata($package)), 'Disk-only custom fields do not appear');
file_put_contents(PACKAGES . 'Example/manifest.json', '{invalid json');
check($service->metadata($package)['version'] === '1.0.0', 'Metadata works with corrupt installed manifest');
rejects(fn() => $service->install($actor, $path, true), 'already installed');
$service->change($actor, $package->id, 'enable');
check($facade->getPackageById($package->id)->status, 'Enable persisted');
$service->change($actor, $package->id, 'enable');
$dependent = array_replace($base, ['name' => 'Dependent', 'dependencies' => [['name' => 'Example', 'version' => '>=1.0.0']]]);
zipPackage(ROOT . 'dependent.zip', $dependent);
$child = $service->install($actor, ROOT . 'dependent.zip', true);
$service->change($actor, $child->id, 'enable');
rejects(fn() => $service->change($actor, $package->id, 'disable'), 'Dependent');
rejects(fn() => $service->change($actor, $package->id, 'remove'), 'Dependent');
$service->change($actor, $child->id, 'disable');
$service->change($actor, $package->id, 'disable');
rejects(fn() => $service->change($actor, $child->id, 'enable'), 'Example');
rejects(fn() => $service->change($actor, $package->id, 'remove'), 'Dependent');
$service->change($actor, $child->id, 'remove');
check(!is_dir(PACKAGES . 'Dependent'), 'Framework removal deletes code');
$system = $facade->getPackage('AWT');
rejects(fn() => $service->change($actor, $system->id, 'disable'), 'System packages');
rejects(fn() => $service->change($actor, $system->id, 'remove'), 'System packages');
$provider->execute('UPDATE awt_admin SET permission_level=1 WHERE id=1');
// Load through a fresh model with its own provider to avoid cached test account state.
$restricted = new class extends admin\Admin {
    public function __construct() { $this->permission_level = 1; }
    public function checkAuthentication(): bool { return true; }
    public function checkPermission(int $level): bool { return false; }
};
rejects(fn() => $service->all($restricted), 'permission 0');
rejects(fn() => $service->change($restricted, $package->id, 'enable'), 'permission 0');
rejects(fn() => (new PackageManager\service\ActionLog())->history($restricted), 'permission 0');
$provider->execute('UPDATE awt_admin SET permission_level=0 WHERE id=1');
$anonymous = new class extends admin\Admin {
    public function __construct() { $this->permission_level = 0; }
    public function checkAuthentication(): bool { return false; }
};
rejects(fn() => PackageManager\service\Access::administrator($anonymous), 'permission 0');
foreach (['../escape.php', '/absolute.php', 'C:/windows.php', 'sub/../../escape.php', 'back\\slash.php'] as $entry) {
    zipPackage(ROOT . 'unsafe.zip', $base, [$entry => 'bad']);
    rejects(fn() => $archive->inspect(ROOT . 'unsafe.zip'), 'unsafe');
}
zipPackage(ROOT . 'system.zip', array_replace($base, ['system_package' => true]));
rejects(fn() => $archive->inspect(ROOT . 'system.zip'), 'System packages');
$zip = new ZipArchive(); $zip->open(ROOT . 'link.zip', ZipArchive::CREATE);
$zip->addFromString('manifest.json', json_encode($base)); $zip->addFromString('main.php', '<?php');
$zip->addFromString('link', '../escape'); $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
rejects(fn() => $archive->inspect(ROOT . 'link.zip'), 'links');
$imagePackage = clone $package;
$imagePackage->icon = '/packages/Example/icon/icon.webp';
$imagePackage->preview_image = 'https://cdn.example.com/previews/preview.webp?version=2';
check(PackageManager\service\Packages::image($imagePackage, 'icon') === $imagePackage->icon, 'Icon uses the database URL unchanged');
check(PackageManager\service\Packages::image($imagePackage, 'preview') === $imagePackage->preview_image, 'Preview uses the database URL unchanged');
$imagePackage->icon = '';
$imagePackage->preview_image = '';
check(PackageManager\service\Packages::image($imagePackage, 'icon') === '/awt_packages/PackageManager/views/assets/images/default_icon.webp', 'Missing icon uses default');
check(PackageManager\service\Packages::image($imagePackage, 'preview') === '/awt_packages/PackageManager/views/assets/images/default_preview.webp', 'Missing preview uses default');
check(str_contains(file_get_contents(dirname(__DIR__) . '/PackageManager/main.php'), "'/awt_packages/PackageManager/data/icon/icon.webp'"), 'Navigation icon retains original static path');

check(PackageManager\service\Packages::licenseUrl('javascript:alert(1)') === null, 'Unsafe license rejected');
$incompatible = clone $package;
$incompatible->minimum_awt_version = '99.0.0';
rejects(fn() => $service->validateAction($incompatible, 'enable', [$package]), 'incompatible');
$incompatible = clone $package;
$incompatible->dependencies = [['name' => 'Example', 'version' => '>=2.0.0']];
rejects(fn() => $service->validateAction($incompatible, 'enable', [$package]), 'Required dependencies');
$lock = fopen(DATA . 'storage/framework/temp/PackageManager/actions.lock', 'c');
flock($lock, LOCK_EX);
rejects(fn() => $service->change($actor, $package->id, 'enable'), 'Another package action');
flock($lock, LOCK_UN); fclose($lock);
$zip = new ZipArchive(); $zip->open(ROOT . 'wrapped.zip', ZipArchive::CREATE);
$zip->addFromString('Example/manifest.json', json_encode($base)); $zip->addFromString('Example/main.php', '<?php'); $zip->close();
rejects(fn() => $archive->inspect(ROOT . 'wrapped.zip'), 'directly at the ZIP root');
zipPackage(ROOT . 'invalid.zip', array_replace($base, ['version' => 'invalid']));
rejects(fn() => $archive->inspect(ROOT . 'invalid.zip'), 'Invalid manifest version');
$history = (new PackageManager\service\ActionLog())->history($actor);
check($history[0]['outcome'] === 'failed', 'Failure logged');
check(count(array_filter($history, fn($row) => $row['action'] === 'install' && $row['package_name'] === 'Example' && $row['outcome'] === 'success')) === 1, 'Installation logs package name');
check($history[0]['account_name'] === 'administrator', 'Actor recorded');
$dependencyPackage = clone $package;
$dependencyPackage->dependencies = [['name' => 'Example', 'version' => '>=1.0.0']];
check($service->dependencies($actor, $dependencyPackage)[0]['status'] === 'disabled', 'Disabled dependency status');
$service->change($actor, $package->id, 'enable');
check($service->dependencies($actor, $dependencyPackage)[0]['status'] === 'installed', 'Installed dependency status');
$dependencyPackage->dependencies = [['name' => 'Example', 'version' => '>=2.0.0']];
check($service->dependencies($actor, $dependencyPackage)[0]['status'] === 'incompatible', 'Incompatible dependency status');
$dependencyPackage->dependencies = [['name' => 'Missing', 'version' => '*']];
check($service->dependencies($actor, $dependencyPackage)[0]['status'] === 'missing', 'Missing dependency status');
check(str_ends_with(PackageManager\service\Packages::image($package, 'icon'), '/default_icon.webp'), 'Default package icon');
check(str_ends_with(PackageManager\service\Packages::image($package, 'preview'), '/default_preview.webp'), 'Default package preview');
check(!is_dir(dirname(__DIR__) . '/PackageManager/cli'), 'No duplicate package CLI commands');
check(!str_contains(file_get_contents(dirname(__DIR__) . '/PackageManager/main.php'), 'CommandProvider'), 'No command provider registration');

// Directory filtering keeps type 0 out without affecting dependency lookups.
$facade->getRepository()->newPackage(array_replace($base, ['name' => 'BlueTheme', 'type' => 2]));
$facade->getRepository()->newPackage(array_replace($base, ['name' => 'FutureExtension', 'type' => 7]));
$catalog = $service->catalog($actor);
check(!in_array('AWT', array_map(fn($item) => $item->name, $catalog['packages']), true), 'Type 0 hidden from directory');
check($catalog['packageTypes'] === ['plugins' => 'Plugins', 'themes' => 'Themes', 'unknown' => 'Unknown'], 'Available types mapped');
check(count($service->catalog($actor, '', 'themes')['packages']) === 1, 'Theme filter');
check($service->catalog($actor, '', 'unknown')['packages'][0]->name === 'FutureExtension', 'Other types grouped as Unknown');
check(count($service->catalog($actor, 'eXaMpLe', 'plugins')['packages']) === 1, 'Search case insensitive and combined with type');
check($service->catalog($actor, 'Example', 'themes')['packages'] === [], 'Search and type intersection can be empty');
check($service->catalog($actor, 'no such package')['packages'] === [], 'Search empty result');
check(count($service->catalog($actor, 'AWT')['packages']) === 3, 'Search includes author');
check($service->catalog($actor, '', 'invalid')['typeFilter'] === '', 'Unknown filter value resets to all');
rejects(fn() => $service->catalog($actor, str_repeat('a', 201)), 'at most 200');
check($facade->getPackage('AWT') !== null, 'Type 0 remains available for dependency checks');
$readmes = new PackageManager\service\PackageReadme();
check($readmes->load($package) === null, 'No README omits documentation');
$markdown = <<<'MARKDOWN'
# Getting started

Read **this guide** and _follow the steps_.

[Documentation](docs/guide.md) and [unsafe](javascript:alert(1)).

![Package icon](data/icon.png)

[Setup](#getting-started) and [Image with spaces](data/my%20image.png).

| Feature | State |
| --- | --- |
| Rendering | Available |

```php
print('example');
```

<div><em>Inline HTML</em></div>
<script>window.readmeExecuted = true;</script>
<img src="data/icon.png" onerror="alert(1)">
<a href="javascript:alert(1)" onclick="alert(1)">Bad link</a>
MARKDOWN;
file_put_contents(PACKAGES . 'Example/README.MD', $markdown);
$readme = $readmes->load($package);
check(str_contains($readme['html'], '>Getting started</h1>') && str_contains($readme['html'], '<strong>this guide</strong>'), 'Markdown headings and emphasis');
check(str_contains($readme['html'], '#pm-readme-content-getting-started') && str_contains($readme['html'], 'id="pm-readme-content-getting-started"'), 'README heading anchors work');
check(str_contains($readme['html'], '/awt_packages/Example/data/my%20image.png'), 'README encoded spaces preserved');
check(str_contains($readme['html'], '<table>') && str_contains($readme['html'], '<pre><code'), 'Markdown tables and fenced code');
check(str_contains($readme['html'], '<em>Inline HTML</em>'), 'Markdown inline HTML supported');
check(!str_contains($readme['html'], '<script') && !str_contains($readme['html'], 'onerror') && !str_contains($readme['html'], 'javascript:'), 'Active README content removed');
check(str_contains($readme['html'], '/awt_packages/Example/docs/guide.md') && str_contains($readme['html'], '/awt_packages/Example/data/icon.png'), 'Relative README URLs resolve in package');
unlink(PACKAGES . 'Example/README.MD');
file_put_contents(PACKAGES . 'Example/readme.html', '<h2>HTML guide</h2><p align="center">Welcome</p><style>body{display:none}</style><svg onload="alert(1)"></svg><a href="//evil.test">Unsafe</a>');
$htmlReadme = $readmes->load($package);
check(str_contains($htmlReadme['html'], '>HTML guide</h2>') && str_contains($htmlReadme['html'], 'pm-readme-align-center'), 'HTML README rendered');
check(!str_contains($htmlReadme['html'], '<style') && !str_contains($htmlReadme['html'], '<svg') && !str_contains($htmlReadme['html'], 'evil.test'), 'HTML active tags and protocol-relative URLs removed');
unlink(PACKAGES . 'Example/readme.html');
file_put_contents(ROOT . 'outside.md', 'private outside README');
symlink(ROOT . 'outside.md', PACKAGES . 'Example/README.md');
check($readmes->load($package) === null, 'README symlink cannot leave package directory');
unlink(PACKAGES . 'Example/README.md');
file_put_contents(PACKAGES . 'Example/README.md', str_repeat('a', 1024 * 1024 + 1));
rejects(fn() => $readmes->load($package), 'too large');
file_put_contents(PACKAGES . 'Example/README.md', $markdown);
$readme = $readmes->load($package);

// Manifest-first dependency review never runs package hooks while preparing archives.
$sourceDownloads = new class extends PackageManager\service\DependencyDownload {
    public array $sources = [];
    public array $requests = [];
    public function fetch(string $url, string $destination): void {
        $this->requests[] = $url;
        if (!isset($this->sources[$url])) throw new RuntimeException('Test source failed.');
        copy($this->sources[$url], $destination);
    }
};
$plans = new PackageManager\service\InstallationPlan(download: $sourceDownloads);
$planBase = array_replace($base, ['name' => 'PlanRoot', 'dependencies' => []]);
zipPackage(ROOT . 'plan-root.zip', $planBase);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
check($plan['ready'] && $plan['order'] === ['PlanRoot'] && !$facade->getPackage('PlanRoot'), 'Review reads manifest without installing');
rejects(fn() => $plans->install($actor, 'wrong-token', true), 'not found');
rejects(fn() => $plans->install($actor, $plan['token'], false), 'Confirm');
$firstPlanToken = $plan['token'];
$planBase['dependencies'] = [['name' => 'PlanMid', 'version' => '>=1.0.0']];
zipPackage(ROOT . 'plan-root.zip', $planBase);
$blockedPlan = $plans->stage($actor, ROOT . 'plan-root.zip');
check(!$blockedPlan['ready'] && count($blockedPlan['blockers']) === 1 && !$blockedPlan['pending'], 'A single missing dependency without a URL blocks installation');
check(str_contains($blockedPlan['blockers'][0], 'find and install it yourself'), 'Missing source explains manual action');
rejects(fn() => $plans->install($actor, $blockedPlan['token'], true), 'Resolve every');
rejects(fn() => $plans->prepare($actor, $firstPlanToken, true, true), 'not found');
$beforeRequests = count($sourceDownloads->requests);
$plans->prepare($actor, $blockedPlan['token'], true, true);
check(count($sourceDownloads->requests) === $beforeRequests, 'Blocked review does not download other packages');

$planBase['dependencies'] = [['name' => 'Example', 'version' => '>=99.0.0', 'url' => 'https://source.test/example.zip']];
zipPackage(ROOT . 'plan-root.zip', $planBase);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
check($plan['dependencies'][0]['status'] === 'incompatible' && $plan['blockers'], 'Installed incompatible dependency needs manual resolution');
$planBase['dependencies'][0]['version'] = '>=1.0.0';
zipPackage(ROOT . 'plan-root.zip', $planBase);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
check($plan['ready'] && $plan['dependencies'][0]['status'] === 'installed', 'Compatible installed dependency allows confirmation');

$planBase['dependencies'] = [['name' => 'PlanMid', 'version' => '>=1.0.0', 'url' => 'https://source.test/mid.zip']];
$midManifest = array_replace($base, ['name' => 'PlanMid', 'dependencies' => [['name' => 'PlanLeaf', 'version' => '>=1.0.0', 'url' => 'https://source.test/leaf.zip']]]);
$leafManifest = array_replace($base, ['name' => 'PlanLeaf', 'dependencies' => []]);
zipPackage(ROOT . 'plan-root.zip', $planBase);
zipPackage(ROOT . 'plan-mid.zip', $midManifest);
zipPackage(ROOT . 'plan-leaf.zip', $leafManifest);
$sourceDownloads->sources = ['https://source.test/mid.zip' => ROOT . 'plan-mid.zip', 'https://source.test/leaf.zip' => ROOT . 'plan-leaf.zip'];
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$missingSourcePlan = $plan;
rejects(fn() => $plans->prepare($actor, $plan['token'], false, false), 'Confirm');
$plan = $plans->prepare($actor, $plan['token'], true, false);
check(count($plan['nodes']) === 2 && $plan['pending'][0]['name'] === 'PlanLeaf' && !$plan['ready'], 'Default recursive review prompts at the next dependency level');
rejects(fn() => $plans->install($actor, $plan['token'], true), 'Resolve every');
$plan = $plans->prepare($actor, $plan['token'], true, true);
check($plan['ready'] && $plan['order'] === ['PlanLeaf', 'PlanMid', 'PlanRoot'], 'Nested dependencies prepare in leaf-first installation order');
check(!$facade->getPackage('PlanRoot') && !$facade->getPackage('PlanMid') && !$facade->getPackage('PlanLeaf'), 'Preparation executes no package installations');
$preparedPlan = $plan;
$installedRoot = $plans->install($actor, $plan['token'], true);
check($installedRoot->name === 'PlanRoot' && !$installedRoot->status && !$facade->getPackage('PlanMid')->status, 'Recursive installation leaves new packages disabled');
check(!$plans->review($actor) && glob(DATA . 'storage/framework/temp/PackageManager/reviews/*.zip') === [], 'Successful installation clears retained review archives');
foreach (['PlanRoot', 'PlanMid', 'PlanLeaf'] as $name) $service->change($actor, $facade->getPackage($name)->id, 'remove');

$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$plan = $plans->prepare($actor, $plan['token'], true, true);
check($plan['ready'] && count($plan['nodes']) === 3 && $plan['skip'], 'Skip checkbox prepares all recursive sources in one confirmation');
$plans->cancel($actor, $plan['token']);
check(glob(DATA . 'storage/framework/temp/PackageManager/reviews/*.zip') === [], 'Cancellation deletes staged archives');

$sourceDownloads->sources['https://source.test/mid.zip'] = ROOT . 'plan-leaf.zip';
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
rejects(fn() => $plans->prepare($actor, $plan['token'], true, true), 'different package');
check(count($plans->review($actor)['nodes']) === 1, 'Wrong source archive is not retained');
$sourceDownloads->sources['https://source.test/mid.zip'] = ROOT . 'plan-mid.zip';
$midManifest['dependencies'] = [['name' => 'PlanRoot', 'version' => '*', 'url' => 'https://source.test/root.zip']];
zipPackage(ROOT . 'plan-mid.zip', $midManifest);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$plan = $plans->prepare($actor, $plan['token'], true, true);
check(!$plan['ready'] && str_contains(implode(' ', $plan['blockers']), 'Circular dependency'), 'Recursive cycles stop before installation');
$plans->cancel($actor, $plan['token']);

$midManifest['dependencies'] = [['name' => 'PlanLeaf', 'version' => '*']];
zipPackage(ROOT . 'plan-mid.zip', $midManifest);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$nestedBlockedPlan = $plans->prepare($actor, $plan['token'], true, true);
check(!$nestedBlockedPlan['ready'] && str_contains(implode(' ', $nestedBlockedPlan['blockers']), 'PlanLeaf'), 'Missing nested URL stops automatic recursion');
$plans->cancel($actor, $nestedBlockedPlan['token']);
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$_SESSION['packagemanager_installation']['expires'] = time() - 1;
rejects(fn() => $plans->review($actor), 'expired');
check(glob(DATA . 'storage/framework/temp/PackageManager/reviews/*.zip') === [], 'Expired review deletes archives');
rejects(fn() => $plans->stage($restricted, ROOT . 'plan-root.zip'), 'permission 0');
$plan = $plans->stage($actor, ROOT . 'plan-root.zip');
$_SESSION['packagemanager_installation']['actor'] = -1;
rejects(fn() => $plans->review($actor), 'expired');
// A later hook failure retains earlier packages but invalidates the review for manual recovery.
$midManifest['dependencies'] = [['name' => 'PlanLeaf', 'version' => '*', 'url' => 'https://source.test/leaf.zip']];
zipPackage(ROOT . 'plan-mid.zip', $midManifest);
zipPackage(ROOT . 'plan-failing-root.zip', $planBase, ['install.php' => '<?php final class PlanFailingInstallHook implements \installer\interfaces\package\actions\IPostInstall { public function postInstall(int $packageId, string $packageName): bool { throw new \RuntimeException("Fixture hook failure"); } }']);
$plan = $plans->stage($actor, ROOT . 'plan-failing-root.zip');
$plan = $plans->prepare($actor, $plan['token'], true, true);
rejects(fn() => $plans->install($actor, $plan['token'], true), 'Fixture hook failure');
check($facade->getPackage('PlanMid') !== null && $facade->getPackage('PlanLeaf') !== null, 'Earlier dependency installs remain after a later hook failure');
check($plans->review($actor) === null && glob(DATA . 'storage/framework/temp/PackageManager/reviews/*.zip') === [], 'Failed installation clears the review to prevent silently skipping failed hooks');
foreach (['PlanRoot', 'PlanMid', 'PlanLeaf'] as $name) $service->change($actor, $facade->getPackage($name)->id, 'remove');

$manyDependencies = [];
for ($i = 0; $i < 30; $i++) $manyDependencies[] = ['name' => 'Missing' . $i, 'version' => '*', 'url' => 'https://source.test/' . $i . '.zip'];
zipPackage(ROOT . 'plan-limit.zip', array_replace($planBase, ['dependencies' => $manyDependencies]));
$plan = $plans->stage($actor, ROOT . 'plan-limit.zip');
check(!$plan['ready'] && str_contains(implode(' ', $plan['blockers']), '30 package limit'), 'Oversized dependency graph is blocked before downloads');
$plans->cancel($actor, $plan['token']);

$publicDownload = new PackageManager\service\DependencyDownload();
foreach (['file:///etc/passwd', 'http://127.0.0.1/package.zip', 'http://10.0.0.1/package.zip', 'http://100.64.0.1/package.zip', 'http://224.0.0.1/package.zip', 'http://user:pass@example.com/package.zip', 'http://example.com:8080/package.zip'] as $source) {
    try { $publicDownload->fetch($source, ROOT . 'download.zip'); throw new LogicException('Unsafe source was accepted'); }
    catch (RuntimeException $error) { check(true, 'Unsafe dependency download rejected'); }
}

// Compile every page through the actual framework Blade engine and Dashboard template.
symlink($dashboard, PACKAGES . 'Dashboard');
$common = ['navigation' => '', 'name' => 'Packages', 'tab' => 'Installed', 'packageTab' => 'overview', 'installation' => null, 'token' => 'test-csrf', 'notice' => null, 'enablePrompt' => null, 'package' => null];
$metadata = $service->metadata($package); unset($metadata['description']);
$views = [
    'index' => array_merge($service->catalog($actor), ['enablePrompt' => ['id' => $package->id, 'name' => 'Example']]),
    'install' => ['tab' => 'Install from disk'],
    'browse' => ['tab' => 'Browse'], 'help' => ['tab' => 'Help'],
    'logs' => ['tab' => 'Logs', 'entries' => $history, 'logPage' => 1, 'hasMore' => true],
    'show' => ['package' => $package, 'metadata' => $metadata, 'readme' => $readme, 'readmeError' => null, 'description' => $base['description'], 'dependencies' => $service->dependencies($actor, $dependencyPackage)],
];
foreach ($views as $view => $data) {
    $blade = new render\TemplateEngine\BladeOne(dirname(__DIR__) . '/PackageManager/views', COMPILED, render\TemplateEngine\BladeOne::MODE_SLOW);
    $blade->setFileExtension('.awt.php');
    $blade->setPackageName('PackageManager');
    $blade->addAssetDict(0, '/awt_packages/PackageManager/views/assets');
    $html = $blade->run($view, array_replace($common, $data));
    check(str_contains($html, 'dashboard-sidebar') && str_contains($html, 'Package management'), $view . ' renders inside Dashboard');
    check(!str_contains($html, '@include') && !str_contains($html, '@if'), $view . ' directives compiled');
    if ($view === 'index') check(!str_contains($html, 'id="pm-upload-form"'), 'Installed page no longer includes ZIP installer');
    if ($view === 'install') {
        check(str_contains($html, 'id="pm-upload-form"') && str_contains($html, 'Installation steps'), 'Install page contains file picker and guidance');
        check(str_contains($html, 'href="/dashboard/packages/install-from-disk" class="active"'), 'Install tab is active');
    }
    if ($view === 'show') {
        check(str_contains($html, 'Installation Date') && str_contains($html, '&lt;script&gt;'), 'All metadata and escaped description');
        check(strpos($html, 'Installation Date') < strpos($html, 'id="pm-description-title"'), 'Description below metadata');
        check(!str_contains($html, 'id="pm-readme-title"') && !str_contains($html, '<iframe'), 'Overview excludes README and license content');
    }
    if ($output = getenv('PM_RENDER_OUTPUT')) {
        if (!is_dir($output)) mkdir($output, 0755, true);
        file_put_contents($output . '/' . $view . '.html', $html);
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    check($xpath->query('//header[contains(@class,"navbar")]//nav[contains(@class,"nav-tabs")]')->length === 1, $view . ' tabs inside header');
    check($xpath->query('//header[contains(@class,"navbar")]//a[@href="/dashboard/packages/logs"]')->length === 0, $view . ' no header logs button');
    if ($view === 'show') {
        check($xpath->query('//header[contains(@class,"navbar")]//div[@class="theme-hero"]//img')->length === 1, 'Package header contains icon');
        check($xpath->query('//section[contains(@class,"pm-overview")]//img[contains(@class,"pm-package-preview")]')->length === 1, 'Preview belongs to Overview');
        check($xpath->query('//nav[@aria-label="Package details"]//a/i')->length === 3, 'Package tabs have Font Awesome icons');
        check($xpath->query('//nav[@aria-label="Package details"]//a[@href="/dashboard/packages"]')->length === 0, 'Details replaces directory tabs');
        check(str_contains($html, 'theme-dependency-missing') && !str_contains($html, '&quot;dependencies&quot;'), 'Dependency statuses rendered without raw JSON');
    }
    check($xpath->query('//form[@method="post"][not(.//input[@name="_token"])]')->length === 0, $view . ' forms include CSRF');
}
foreach (['missing' => $missingSourcePlan, 'blocked' => $blockedPlan, 'prepared' => $preparedPlan, 'nested-blocked' => $nestedBlockedPlan] as $state => $installation) {
    $html = $blade->run('install', array_replace($common, ['tab' => 'Install from disk', 'installation' => $installation]));
    check(str_contains($html, 'Dependency check') && !str_contains($html, '@if'), 'Installation review renders ' . $state);
    check(str_contains($html, 'name="installation_token"') && str_contains($html, 'name="_token"'), 'Review forms include installation and CSRF tokens');
    if ($state === 'blocked' || $state === 'nested-blocked') check(!str_contains($html, 'action="/dashboard/packages/install/dependencies"') && !str_contains($html, 'action="/dashboard/packages/install"'), 'Blocked review has no install actions');
    if ($state === 'missing') check(str_contains($html, 'skip_prompts') && str_contains($html, 'https://source.test/mid.zip'), 'Missing source dependencies show recursive preparation option');
    if ($state === 'prepared') check(str_contains($html, 'action="/dashboard/packages/install"') && str_contains($html, 'All dependencies are available'), 'Prepared review offers final confirmation');
    if ($output = getenv('PM_RENDER_OUTPUT')) file_put_contents($output . '/install-' . $state . '.html', $html);
}
// Render optional pages and verify shared actions and conditional navigation.
foreach (['readme', 'license'] as $packageTab) {
    $html = $blade->run('show', array_replace($common, $views['show'], ['packageTab' => $packageTab]));
    check(str_contains($html, 'id="pm-actions-title"') && str_contains($html, 'name="operation"'), $packageTab . ' keeps package actions');
    check(!str_contains($html, 'id="pm-dependencies-title"') && !str_contains($html, 'id="metadata-title"'), $packageTab . ' excludes Overview content');
    if ($packageTab === 'readme') check(str_contains($html, '>Getting started</h1>') && !str_contains($html, '<iframe'), 'README tab renders sanitized documentation only');
    if ($packageTab === 'license') check(str_contains($html, 'sandbox') && str_contains($html, 'Open original license'), 'License tab embeds document with original link');
    if ($output = getenv('PM_RENDER_OUTPUT')) file_put_contents($output . '/show-' . $packageTab . '.html', $html);
}
$noDocs = clone $package; $noDocs->license = ''; $noDocs->license_url = null;
$html = $blade->run('show', array_replace($common, $views['show'], ['package' => $noDocs, 'readme' => null]));
check(!str_contains($html, '?tab=readme') && !str_contains($html, '?tab=license'), 'Unavailable README and license tabs are omitted');
$noDocs->license = 'MIT';
$html = $blade->run('show', array_replace($common, $views['show'], ['package' => $noDocs, 'readme' => null, 'packageTab' => 'license']));
check(str_contains($html, '?tab=license') && str_contains($html, 'licensed under MIT') && !str_contains($html, '<iframe'), 'Named license works without a document URL');
// Exercise the actual runtime factory and controller render pipeline too.
require_once dirname(__DIR__) . '/PackageManager/runtime/PackageManagerControllerAPI.php';
$runtime = new PackageManagerControllerAPI();
foreach (['runtimePath' => dirname(__DIR__) . '/PackageManager', 'rootPath' => dirname(__DIR__) . '/PackageManager', 'name' => 'PackageManager', 'id' => 1] as $property => $value) {
    (new ReflectionProperty($runtime, $property))->setValue($runtime, $value);
}
$runtime->eventDispatcher = new event\EventDispatcher();
$runtime->setup(); $runtime->main();
$factory = $runtime->getController('PackageManagerController');
foreach (['index', 'browse', 'installFromDisk', 'help', 'logs'] as $action) {
    $controller = (clone $factory)->create();
    $controller->{$action}([]);
    check(str_contains($controller->render(), 'dashboard-sidebar'), 'Actual controller renders ' . $action);
}
$controller = (clone $factory)->create();
unlink(PACKAGES . 'Example/manifest.json');
$controller->show(['package_id' => $package->id]);
check(str_contains($controller->render(), 'Installation Date'), 'Actual controller renders database metadata without manifest file');
check(!str_contains($controller->render(), 'Custom Field'), 'Controller omits disk-only metadata');
(new database\DatabaseManager())->table('awt_package')->where(['id' => $package->id])->update(['description' => 'Database description takes precedence']);
$controller = (clone $factory)->create(); $controller->show(['package_id' => $package->id]);
check(str_contains($controller->render(), 'Database description takes precedence'), 'Description uses current database value');

foreach (['overview', 'readme', 'license', 'unknown', ['readme']] as $requestedTab) {
    $_GET = ['tab' => $requestedTab];
    $controller = (clone $factory)->create(); $controller->show(['package_id' => $package->id]);
    $expectedTab = is_string($requestedTab) && in_array($requestedTab, ['readme', 'license'], true) ? $requestedTab : 'overview';
    check($controller->bundle['packageTab'] === $expectedTab, 'Controller selects available tabs and defaults invalid input');
    check(str_contains($controller->render(), 'id="pm-actions-title"'), 'Controller retains actions on every tab');
}
$_GET = [];

$_GET = ['q' => 'example', 'type' => 'plugins'];
$controller = (clone $factory)->create();
$controller->index([]);
check(count($controller->bundle['packages']) === 1 && $controller->bundle['packages'][0]->name === 'Example', 'Controller applies GET search and type');
$filteredHtml = $controller->render();
check(str_contains($filteredHtml, 'Clear filters') && str_contains($filteredHtml, 'value="example"'), 'Filtered page preserves query and provides reset');
$_GET = ['q' => 'no such package'];
$controller = (clone $factory)->create(); $controller->index([]);
check(str_contains($controller->render(), 'No packages match'), 'No-match page has useful empty state');
$_GET = [];
if ($output = getenv('PM_RENDER_OUTPUT')) file_put_contents($output . '/index-filtered.html', $filteredHtml);

$_SERVER['REQUEST_URI'] = '/dashboard/packages/install';
$_FILES = [];
$controller = (clone $factory)->create();
check($controller->install([])->getRedirectTo() === '/dashboard/packages/install-from-disk', 'Upload errors return to installer');
$controller->installFromDisk([]);
check(str_contains($controller->render(), 'Installation failed:'), 'Installer displays upload failure notice');

$endpointManifest = array_replace($base, ['name' => 'EndpointPackage', 'dependencies' => []]);
zipPackage(ROOT . 'endpoint.zip', $endpointManifest);
$endpointPlans = new PackageManager\service\InstallationPlan();
$endpointPlan = $endpointPlans->stage($actor, ROOT . 'endpoint.zip');
$controller = (clone $factory)->create(); $controller->installFromDisk([]);
check(str_contains($controller->render(), 'Review EndpointPackage'), 'Controller renders retained manifest review on the same screen');
$_POST = ['installation_token' => $endpointPlan['token']];
check($controller->install([])->getRedirectTo() === '/dashboard/packages/install-from-disk' && !$facade->getPackage('EndpointPackage'), 'Install endpoint requires final trust confirmation');
$_POST['confirmed'] = '1';
check($controller->install([])->getRedirectTo() === '/dashboard/packages' && $facade->getPackage('EndpointPackage') !== null, 'Install endpoint installs the reviewed archive');
check($_SESSION['packagemanager_enable']['name'] === 'EndpointPackage', 'Successful reviewed installation offers enable prompt');
$service->change($actor, $facade->getPackage('EndpointPackage')->id, 'remove');
unset($_SESSION['packagemanager_enable']);
$endpointPlan = $endpointPlans->stage($actor, ROOT . 'endpoint.zip');
$_POST = ['installation_token' => $endpointPlan['token']];
check($controller->cancelInstallation([])->getRedirectTo() === '/dashboard/packages/install-from-disk' && $endpointPlans->review($actor) === null, 'Cancel endpoint clears review and returns to file picker');
$_POST = [];

// Enable/disable return to the originating package tab, including failures.
$_SERVER['REQUEST_URI'] = '/dashboard/packages/action';
foreach (['overview', 'readme', 'license'] as $returnTab) {
    $_POST = ['package_id' => $package->id, 'operation' => 'enable', 'return_tab' => $returnTab];
    $controller = (clone $factory)->create();
    $redirect = $controller->action([]);
    check($redirect->getRedirectTo() === '/dashboard/packages/' . $package->id . ($returnTab === 'overview' ? '' : '?tab=' . $returnTab), 'Enable returns to package tab');
    $_POST['operation'] = 'disable';
    check($controller->action([])->getRedirectTo() === $redirect->getRedirectTo(), 'Disable returns to package tab');
}
$_POST = ['package_id' => $system->id, 'operation' => 'disable', 'return_tab' => 'license'];
check($controller->action([])->getRedirectTo() === '/dashboard/packages/' . $system->id . '?tab=license' && $_SESSION['packagemanager_notice']['kind'] === 'error', 'Failed action keeps originating tab and notice');
foreach ([null, 'https://example.test', ['overview']] as $returnTab) {
    $_POST = ['package_id' => $package->id, 'operation' => 'enable', 'return_tab' => $returnTab];
    check($controller->action([])->getRedirectTo() === '/dashboard/packages', 'Home actions and invalid return tabs use package list');
}
$_POST = ['package_id' => $system->id, 'operation' => 'remove', 'confirmed' => '1', 'return_tab' => 'license'];
check($controller->action([])->getRedirectTo() === '/dashboard/packages', 'Remove always returns to package list');
$_POST = [];

$widget = new PackageManager\widgets\RecentPackageActions();
check(count($widget->widget()->getBlockChildren()) >= 4, 'Administrator widget executes');
(new database\DatabaseManager())->table('awt_admin')->where(['id' => 1])->update(['permission_level' => 1]);
rejects(fn() => (new PackageManager\widgets\RecentPackageActions())->widget(), 'permission 0');
(new database\DatabaseManager())->table('awt_admin')->where(['id' => 1])->update(['permission_level' => 0]);
$item = new Dashboard\api\navigation\NavigationItem('Packages', '/dashboard/packages', permissionLevel: 0);
check((new Dashboard\api\navigation\NavigationContainer([$item], '/', $restricted))->getNavigationItems() === [], 'Navigation hidden from restricted account');
$route = (new router\Router('/dashboard/packages/action', 'action', clone $factory))->setMethod('POST');
$executor = new router\execution\RouteExecutor();
$rejected = $executor->execute($route, new router\http\Request(['REQUEST_METHOD' => 'POST'], ['_token' => 'forged']));
check($rejected instanceof response\Response && (new ReflectionProperty($rejected, 'statusCode'))->getValue($rejected) === 403, 'Real mutation route rejects forged CSRF');
$rejected = $executor->execute($route, new router\http\Request(['REQUEST_METHOD' => 'GET']));
check($rejected instanceof response\Response && (new ReflectionProperty($rejected, 'statusCode'))->getValue($rejected) === 405, 'Real mutation route rejects GET');
foreach (['/install/review' => 'reviewInstallation', '/install/dependencies' => 'prepareDependencies', '/install/cancel' => 'cancelInstallation', '/install' => 'install'] as $path => $action) {
    $route = (new router\Router('/dashboard/packages' . $path, $action, clone $factory))->setMethod('POST');
    $rejected = $executor->execute($route, new router\http\Request(['REQUEST_METHOD' => 'POST'], ['_token' => 'forged']));
    check($rejected instanceof response\Response && (new ReflectionProperty($rejected, 'statusCode'))->getValue($rejected) === 403, 'Installation mutation rejects forged CSRF');
    $rejected = $executor->execute($route, new router\http\Request(['REQUEST_METHOD' => 'GET']));
    check($rejected instanceof response\Response && (new ReflectionProperty($rejected, 'statusCode'))->getValue($rejected) === 405, 'Installation mutation rejects GET');
}
$csrf = new router\security\Csrf();
check($csrf->validateToken($csrf->token()), 'Framework CSRF accepts own token');
check(!$csrf->validateToken('invalid'), 'Framework CSRF rejects forged token');
echo "Passed {$checks} integration checks.\n";
