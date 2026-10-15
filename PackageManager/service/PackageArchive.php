<?php

namespace PackageManager\service;

use installer\package\Extractor;
use installer\package\PackageInstaller;
use installer\package\PackageMover;
use installer\package\PackageStorageTreeGenerator;
use package\manifest\reader\ManifestReader;
use package\model\repository\PackageRepository;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;
use vfs\transient\enums\ETransientType;
use vfs\transient\TransientStorage;
use ZipArchive;

final class PackageArchive
{
    public const MAX_BYTES = 50 * 1024 * 1024;

    public function uploadPath(array $upload): string
    {
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)
            || !is_uploaded_file($upload['tmp_name'])) {
            throw new \InvalidArgumentException('Choose a ZIP file up to 50 MB. Check the server upload limit if the upload fails.');
        }
        return $upload['tmp_name'];
    }

    /** Validate before the framework extracts or executes anything. */
    public function inspect(string $path): array
    {
        $size = is_file($path) ? filesize($path) : false;
        if (!$size || $size > self::MAX_BYTES) throw new \InvalidArgumentException('Choose a nonempty ZIP file up to 50 MB.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new \InvalidArgumentException('The file is not a readable ZIP archive.');
        try {
            if ($zip->numFiles > 10000) throw new \InvalidArgumentException('The archive has too many entries (maximum 10,000).');
            $total = 0;
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if ($name === '' || preg_match('~[\\\\\x00-\x1f:]|^/|(?:^|/)\.{1,2}(?:/|$)~', $name)
                    || isset($seen[strtolower(rtrim($name, '/'))])) {
                    throw new \InvalidArgumentException('The archive contains unsafe or duplicate paths.');
                }
                $seen[strtolower(rtrim($name, '/'))] = true;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                $mode = ($attributes >> 16) & 0170000;
                if (!in_array($mode, [0, 0100000, 0040000], true)) {
                    throw new \InvalidArgumentException('Archive links and special files are not supported.');
                }
                $total += $entry['size'];
                if ($total > 200 * 1024 * 1024) throw new \InvalidArgumentException('The extracted package exceeds 200 MB.');
            }
            $json = $zip->getFromName('manifest.json');
            if (!is_string($json) || strlen($json) > 1024 * 1024 || $zip->locateName('main.php') === false) {
                throw new \InvalidArgumentException('manifest.json and main.php must be directly at the ZIP root.');
            }
            $manifest = ManifestReader::validate(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
            // Uploaded system packages would be automatically enabled by the framework.
            if (!empty($manifest['system_package']) || $manifest['type'] === 0) {
                throw new \InvalidArgumentException('System packages must be installed through the trusted server deployment process.');
            }
            return $manifest;
        } finally { $zip->close(); }
    }

    public function install(string $path): string
    {
        $manifest = $this->inspect($path);
        $destination = rtrim(PACKAGES, '/') . '/' . str_replace(' ', '', $manifest['name']);
        if (file_exists($destination) || is_link($destination)) {
            throw new \InvalidArgumentException('A package directory with this name already exists. Resolve it before installing.');
        }
        // Archives stay in the framework's private transient VFS, never public storage.
        $pool = (new TransientStorage())->setPool('temp')->setSubPool('PackageManager');
        $content = file_get_contents($path);
        if ($content === false) throw new \RuntimeException('Unable to read the package upload.');
        $entry = $pool->createFile(bin2hex(random_bytes(16)), ETransientType::ARCHIVE, $content);
        try {
            $extractor = new Extractor(new ZipArchive(), $entry, dirname($entry->getPath()) . '/extract-');
            $installer = new PackageInstaller(
                $extractor, new PackageMover('', PACKAGES),
                new PackageStorageTreeGenerator(new LocalFileSystemService(), new StorageRepository()),
                new PackageRepository(),
            );
            if (!$installer->install()) throw new \RuntimeException('The framework rejected the package structure or manifest.');
            return $manifest['name'];
        } finally {
            if (!$entry->delete()) error_log('PackageManager: unable to delete temporary archive.');
        }
    }
}
