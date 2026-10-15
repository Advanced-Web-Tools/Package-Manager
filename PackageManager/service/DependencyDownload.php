<?php

namespace PackageManager\service;

/** Download public HTTP(S) archives with bounded size and redirect validation. */
class DependencyDownload
{
    public function fetch(string $url, string $destination): void
    {
        if (!extension_loaded('curl')) throw new \RuntimeException('The cURL extension is required to download dependencies.');
        $handle = fopen($destination, 'wb');
        if (!$handle) throw new \RuntimeException('Unable to store the dependency archive.');
        try {
            for ($redirect = 0; $redirect < 6; $redirect++) {
                $parts = parse_url($url);
                if (!Packages::licenseUrl($url) || !$parts || isset($parts['user']) || isset($parts['pass'])) {
                    throw new \RuntimeException('Dependency sources must be public HTTP or HTTPS URLs without credentials.');
                }
                $host = $parts['host'];
                $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
                if (!in_array($port, [80, 443], true)) throw new \RuntimeException('Dependency sources must use port 80 or 443.');
                $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
                if (!$addresses) throw new \RuntimeException('Unable to resolve dependency source.');
                foreach ($addresses as $address) {
                    if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                        || (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int) explode('.', $address)[0] >= 224)) {
                        throw new \RuntimeException('Dependency sources must resolve to public addresses.');
                    }
                }
                ftruncate($handle, 0); rewind($handle);
                $size = 0;
                $curl = curl_init($url);
                try {
                    curl_setopt_array($curl, [
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60,
                        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $addresses[0]],
                        CURLOPT_USERAGENT => 'AWT-PackageManager/1.0',
                        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$size): int {
                            $size += strlen($chunk);
                            if ($size > PackageArchive::MAX_BYTES) return 0;
                            return fwrite($handle, $chunk);
                        },
                    ]);
                    $result = curl_exec($curl);
                    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    $next = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
                    if ($result === false) throw new \RuntimeException('Dependency download failed or exceeded 50 MB.');
                    if (in_array($status, [301, 302, 303, 307, 308], true) && $next) { $url = $next; continue; }
                    if ($status !== 200 || $size === 0) throw new \RuntimeException('Dependency source did not return a package ZIP (HTTP ' . $status . ').');
                    return;
                } finally { curl_close($curl); }
            }
            throw new \RuntimeException('Dependency source redirected too many times.');
        } finally { fclose($handle); }
    }
}
