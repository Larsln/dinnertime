<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fetches links that users hand in, but only from the public internet.
 *
 * Every host is resolved first and refused when any of its addresses lies
 * in a private, local or reserved network, so an import cannot reach
 * services inside the home network. The request is pinned to the checked
 * address, so a second lookup cannot lead elsewhere, and redirects are
 * followed one by one so every hop passes the same check.
 */
class PublicFetcher
{
    private const int MAX_REDIRECTS = 5;

    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly ImportSettings $settings,
    ) {}

    /**
     * Send a GET request to the link and answer the final response.
     *
     * @throws ImportFailedException
     */
    public function get(string $url): Response
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            try {
                $response = Http::withUserAgent($this->settings->userAgent())
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->withOptions(['allow_redirects' => false, 'curl' => $this->pinnedAddress($url)])
                    ->get($url);
            } catch (ConnectionException $exception) {
                throw new ImportFailedException(ImportError::DownloadFailed, $exception->getMessage());
            }

            if (! $response->redirect() || blank($response->header('Location'))) {
                return $response;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
        }

        throw new ImportFailedException(ImportError::DownloadFailed, 'Too many redirects');
    }

    /**
     * Curl options that make the request connect to the checked address.
     *
     * @return array<int, mixed>
     *
     * @throws ImportFailedException
     */
    private function pinnedAddress(string $url): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ImportFailedException(ImportError::DownloadFailed, "Refused to fetch {$url}");
        }

        $address = $this->publicAddress($host);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80);
        $pinned = str_contains($address, ':') ? "[{$address}]" : $address;

        return [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]];
    }

    /**
     * The address to connect to, provided every address of the host is a
     * public one.
     *
     * @throws ImportFailedException
     */
    private function publicAddress(string $host): string
    {
        $addresses = $this->resolver->addresses($host);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new ImportFailedException(ImportError::DownloadFailed, "Refused to fetch from the local address of {$host}");
            }
        }

        return $addresses[0] ?? throw new ImportFailedException(ImportError::DownloadFailed, "Unknown host {$host}");
    }
}
