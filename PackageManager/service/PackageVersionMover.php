<?php

namespace PackageManager\service;

use FilesystemIterator;
use installer\package\PackageMover;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Replace the installed tree so files removed by the selected release cannot still run. */
final class PackageVersionMover extends PackageMover
{
    private ?string $backup = null;

    public function move(): void
    {
        $destination = $this->getDestination();
        $backup = dirname($destination) . '/.pm-' . bin2hex(random_bytes(16));
        if (!rename($destination, $backup)) throw new RuntimeException('Unable to back up the installed package files.');
        $this->backup = $backup;
        if (!mkdir($destination, 0755)) throw new RuntimeException('Unable to create the replacement package directory.');
        parent::move();
    }

    public function finish(bool $registered): void
    {
        if ($this->backup === null) return;
        $destination = $this->getDestination();
        $remove = $registered ? $this->backup : $destination;
        // Restore the original directory before cleaning up a failed replacement.
        if (!$registered) {
            $remove = $this->backup . '-failed';
            if ((is_dir($destination) && !rename($destination, $remove)) || !rename($this->backup, $destination)) {
                throw new RuntimeException('Unable to restore the previous package directory. Check the server package files.');
            }
        }
        if (!is_dir($remove)) return;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($remove, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($remove);
    }
}
