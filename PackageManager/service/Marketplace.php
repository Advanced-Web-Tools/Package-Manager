<?php

namespace PackageManager\service;

use InvalidArgumentException;
use RuntimeException;

final class Marketplace
{
    public const SECTIONS = [
        'featured' => 'Featured', 'trending' => 'Trending', 'top-rated' => 'Top rated',
        'most-downloaded' => 'Most downloaded', 'newest' => 'Newest',
    ];
    private const PREFIX = '/api/v1/marketplace';

    public readonly string $origin;

    public function __construct(?string $origin = null, private readonly DependencyDownload $download = new DependencyDownload())
    {
        $origin ??= getenv('AWT_MARKETPLACE_ORIGIN') ?: 'https://store.advancedwebtools.com';
        if (!preg_match('~\Ahttps?://[a-z0-9.-]+/?\z~i', $origin)) {
            throw new InvalidArgumentException('Configure a valid Marketplace origin in AWT_MARKETPLACE_ORIGIN.');
        }
        $this->origin = rtrim($origin, '/');
    }

    public function get(string $path, array $query = []): array
    {
        $url = $this->origin . self::PREFIX . $path;
        if ($query) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $file = self::temporaryFile();
        try {
            try {
                $this->download->transfer($url, $file, 4 * 1024 * 1024, $this->origin);
            } catch (RuntimeException $error) {
                if ($error->getCode() >= 400) {
                    $response = json_decode(file_get_contents($file), true);
                    $message = $response['message'] ?? null;
                    if (is_string($message) && $message !== '') {
                        throw new RuntimeException($message, $error->getCode(), $error);
                    }
                }
                throw $error;
            }
            $response = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (($response['status'] ?? null) !== 200 || !is_array($response['data'] ?? null)) {
                throw new RuntimeException('Marketplace returned an invalid response.');
            }
            return $response['data'];
        } finally { unlink($file); }
    }

    public static function compatibility(): array
    {
        $version = defined('AWT_VERSION') ? AWT_VERSION : '';
        return preg_match('/\A\d+(?:\.\d+){0,2}\z/', $version) && strlen($version) <= 32
            ? ['awt_current_version' => $version] : [];
    }

    public function latest(int $id): array
    {
        return $this->get("/package/{$id}/latest", self::compatibility());
    }

    public function latestForPackage(int $id, string $name): array
    {
        $release = $this->latest($id);
        if (($release['package_id'] ?? null) !== $id || ($release['name'] ?? null) !== $name
            || !is_int($release['id'] ?? null) || $release['id'] < 1 || !is_string($release['version'] ?? null)) {
            throw new RuntimeException('Marketplace returned an unexpected release.');
        }
        return $release;
    }

    public function source(int $id, int $versionId): array
    {
        $url = $this->origin . self::PREFIX . "/package/{$id}";
        return ['storefront_id' => $id, 'release_id' => $versionId,
            'latest_url' => $url . '/latest', 'download_url' => $url . "/versions/{$versionId}/download"];
    }

    public static function fromSource(array $source, DependencyDownload $download = new DependencyDownload()): self
    {
        $suffix = self::PREFIX . '/package/' . (int) $source['storefront_id'] . '/latest';
        if (!str_ends_with($source['latest_url'], $suffix)) throw new RuntimeException('Invalid stored Marketplace source.');
        return new self(substr($source['latest_url'], 0, -strlen($suffix)), $download);
    }

    public function download(array $source, string $destination): void
    {
        $this->download->transfer($source['download_url'], $destination, PackageArchive::MAX_BYTES, $this->origin);
    }

    public function media(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') return null;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) $url = $this->origin . $url;
        return Packages::licenseUrl($url);
    }

    public function artwork(array $item): array
    {
        $cover = $this->media($item['cover_url'] ?? null);
        $video = $this->media($item['cover_video_url'] ?? null);
        if ($cover && preg_match('/\.(mp4|webm)\z/i', parse_url($cover, PHP_URL_PATH) ?? '')) {
            $video ??= $cover;
            $cover = null;
        }
        return [
            'cover' => $cover ?? '/awt_packages/PackageManager/views/assets/images/default_preview.webp',
            'video' => $video,
            'logo' => $this->media($item['logo_url'] ?? null) ?? '/awt_packages/PackageManager/views/assets/images/default_icon.webp',
        ];
    }

    public function gallery(?array $gallery): ?array
    {
        if ($gallery === null) return null;
        $items = [];
        foreach ($gallery['items'] as $item) {
            $url = $this->media($item['url']);
            if ($url && in_array($item['type'], ['image', 'video'], true)) {
                $items[] = ['url' => $url, 'type' => $item['type']];
            }
        }
        return $items ? ['name' => $gallery['name'], 'description_markup' => (new PackageReadme())->description($gallery['description_html'] ?? $gallery['description'] ?? null, marketplace: $this), 'items' => $items] : null;
    }

    public function creatorAvatar(array $profile): ?string
    {
        $avatar = $this->media($profile['avatar_url'] ?? null);
        $organizationId = (int) ($profile['organizations'][0]['id'] ?? 0);
        if ($avatar || !$organizationId) return $avatar;
        try {
            $organization = $this->get("/organizations/{$organizationId}");
            foreach ($organization['members'] ?? [] as $member) {
                if ((int) $member['id'] === (int) $profile['id']) {
                    return $this->media($member['avatar_url'] ?? null);
                }
            }
        } catch (RuntimeException | \JsonException $error) {
            // The profile can still be rendered with its initials.
        }
        return null;
    }

    public function profileRelationships(array $profile, string $type): array
    {
        $members = $type === 'organization';
        $people = [];
        foreach ($profile[$members ? 'members' : 'organizations'] ?? [] as $entry) {
            $id = filter_var($entry['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) continue;
            $people[] = [
                'name' => $entry['name'],
                'image' => $this->media($entry[$members ? 'avatar_url' : 'logo_url'] ?? null),
                'meta' => $members ? (!empty($entry['is_owner']) ? 'Owner' : '@' . $entry['username']) : null,
                'url' => '/dashboard/packages/browse?' . http_build_query([
                    'owner' => $members ? 'creator' : 'organization', 'profile' => $id,
                ]),
            ];
        }
        return $people;
    }

    public static function temporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'awt-marketplace-');
        if ($file === false) throw new RuntimeException('Unable to create a temporary Marketplace file.');
        return $file;
    }
}
