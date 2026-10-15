<?php

namespace PackageManager\service;

use DOMDocument;
use DOMElement;
use DOMNode;
use package\model\InstalledPackage;

/** Read package documentation without executing package PHP or embedding active HTML. */
final class PackageReadme
{
    private const MAX_BYTES = 1024 * 1024;
    private const TAGS = ['a', 'p', 'div', 'span', 'section', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 's', 'del', 'blockquote', 'pre', 'code', 'kbd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'img', 'br', 'hr', 'details', 'summary',
        'dl', 'dt', 'dd', 'sup', 'sub'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form',
        'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'noscript'];

    /** @return array{filename:string, html:string}|null */
    public function load(InstalledPackage $package): ?array
    {
        $root = realpath($package->getPackagePath());
        if (!$root || !is_dir($root)) return null;
        $files = scandir($root);
        if ($files === false) throw new \RuntimeException('The package README directory could not be read.');
        // Prefer Markdown when a package includes several README formats.
        foreach (['md', 'markdown', 'html', 'htm', ''] as $extension) {
            foreach ($files as $filename) {
                if (strtolower($filename) !== 'readme' . ($extension === '' ? '' : '.' . $extension)) continue;
                $path = realpath($root . '/' . $filename);
                if (!$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) continue;
                if (!is_readable($path)) throw new \RuntimeException('The package README could not be read.');
                $size = filesize($path);
                if ($size === false || $size > self::MAX_BYTES) throw new \RuntimeException('This README is too large to display (maximum 1 MB).');
                $source = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
                if ($source === false || strlen($source) > self::MAX_BYTES) throw new \RuntimeException('The package README could not be read or exceeds 1 MB.');
                if (!preg_match('//u', $source)) throw new \RuntimeException('The package README must use UTF-8 text.');
                if (!in_array($extension, ['html', 'htm'], true)) {
                    require_once dirname(__DIR__) . '/vendor/parsedown/Parsedown.php';
                    $source = (new \PackageManager\vendor\parsedown\Parsedown())->text($source);
                }
                return ['filename' => $filename, 'html' => $this->sanitize($source, $package)];
            }
        }
        return null;
    }

    private function sanitize(string $html, InstalledPackage $package): string
    {
        $source = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $source->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $output = new DOMDocument('1.0', 'UTF-8');
        $container = $output->createElement('div');
        $output->appendChild($container);
        foreach ($source->getElementsByTagName('body') as $body) {
            foreach ($body->childNodes as $child) $this->copy($child, $container, $output, $package, 0);
        }
        $result = '';
        foreach ($container->childNodes as $node) $result .= $output->saveHTML($node);
        return $result;
    }

    private function copy(DOMNode $source, DOMNode $parent, DOMDocument $output, InstalledPackage $package, int $depth): void
    {
        if ($depth > 100) return;
        if ($source->nodeType === XML_TEXT_NODE) {
            $parent->appendChild($output->createTextNode($source->nodeValue));
            return;
        }
        if (!$source instanceof DOMElement) return;
        $tag = strtolower($source->tagName);
        if (in_array($tag, self::DROP, true)) return;
        $target = $parent;
        if (in_array($tag, self::TAGS, true)) {
            $target = $output->createElement($tag);
            $parent->appendChild($target);
            foreach (['title', 'alt'] as $attribute) {
                if ($source->hasAttribute($attribute)) $target->setAttribute($attribute, $source->getAttribute($attribute));
            }
            // Preserve README anchor navigation without exposing arbitrary CSS or handlers.
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,100}$/', $source->getAttribute('id'))) {
                $target->setAttribute('id', 'pm-readme-content-' . $source->getAttribute('id'));
            }
            if (preg_match('/^h[1-6]$/', $tag) && !$target->hasAttribute('id')) {
                $slug = trim(preg_replace('/[^\p{L}\p{N}_-]+/u', '-', strtolower(trim($source->textContent))), '-');
                if ($slug !== '') $target->setAttribute('id', 'pm-readme-content-' . $slug);
            }
            if ($source->hasAttribute('align') && in_array($source->getAttribute('align'), ['left', 'center', 'right'], true)) {
                $target->setAttribute('class', 'pm-readme-align-' . $source->getAttribute('align'));
            }
            if ($tag === 'a') {
                $url = $this->url($source->getAttribute('href'), $package, false);
                if ($url !== null) $target->setAttribute('href', $url);
                $target->setAttribute('rel', 'noopener noreferrer');
            }
            if ($tag === 'img') {
                $url = $this->url($source->getAttribute('src'), $package, true);
                if ($url !== null) $target->setAttribute('src', $url);
                $target->setAttribute('loading', 'lazy');
                $target->setAttribute('referrerpolicy', 'no-referrer');
                foreach (['width', 'height'] as $dimension) {
                    if (preg_match('/^[1-9][0-9]{0,3}$/', $source->getAttribute($dimension))) $target->setAttribute($dimension, $source->getAttribute($dimension));
                }
            }
            if ($tag === 'ol' && preg_match('/^[0-9]{1,6}$/', $source->getAttribute('start'))) $target->setAttribute('start', $source->getAttribute('start'));
            if ($tag === 'details' && $source->hasAttribute('open')) $target->setAttribute('open', '');
        }
        foreach ($source->childNodes as $child) $this->copy($child, $target, $output, $package, $depth + 1);
    }

    private function url(string $value, InstalledPackage $package, bool $image): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20\\\\]/', $value) || str_starts_with($value, '//')) return null;
        if (str_starts_with($value, '#')) return '#pm-readme-content-' . substr($value, 1);
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme !== null && $scheme !== false) {
            return in_array(strtolower($scheme), $image ? ['http', 'https'] : ['http', 'https', 'mailto'], true) ? $value : null;
        }
        if (str_starts_with($value, '/')) return $value;
        $parts = parse_url($value);
        if ($parts === false) return null;
        $segments = [];
        foreach (explode('/', rawurldecode($parts['path'] ?? '')) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                if (!$segments) return null;
                array_pop($segments);
            } else {
                if (preg_match('/[\x00-\x1f\\\\:]/', $segment)) return null;
                $segments[] = rawurlencode($segment);
            }
        }
        return '/awt_packages/' . rawurlencode(str_replace(' ', '', $package->name)) . '/' . implode('/', $segments)
            . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
