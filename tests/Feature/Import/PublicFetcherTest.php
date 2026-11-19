<?php

namespace Tests\Feature\Import;

use App\Enums\ImportError;
use App\Services\Import\HostResolver;
use App\Services\Import\ImportFailedException;
use App\Services\Import\PublicFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class PublicFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->app->instance(HostResolver::class, new FakeHostResolver([
            'nas.home.test' => ['192.168.1.20'],
            'mixed.test' => [FakeHostResolver::PUBLIC_ADDRESS, '10.0.0.5'],
            'tailnet.test' => ['100.101.102.103'],
            'v6.test' => ['fd00::20'],
            'unknown.test' => [],
        ]));
    }

    public function test_it_fetches_public_pages(): void
    {
        Http::fake(['blog.test/*' => Http::response('<html>Lasagne</html>')]);

        $this->assertSame('<html>Lasagne</html>', $this->fetcher()->get('https://blog.test/lasagne')->body());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function internalLinks(): array
    {
        return [
            'private host' => ['http://nas.home.test/admin'],
            'host with one private address' => ['https://mixed.test/'],
            'tailscale address' => ['http://tailnet.test/'],
            'unique local IPv6' => ['http://v6.test/'],
            'loopback' => ['http://127.0.0.1:8080/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'IPv6 loopback' => ['http://[::1]/'],
            'unknown host' => ['https://unknown.test/'],
            'other scheme' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('internalLinks')]
    public function test_it_refuses_addresses_inside_the_network(string $url): void
    {
        Http::fake();

        $this->assertRefused(fn () => $this->fetcher()->get($url));
        Http::assertNothingSent();
    }

    public function test_it_follows_redirects_to_public_pages(): void
    {
        Http::fake([
            'short.test/*' => Http::response(status: 301, headers: ['Location' => 'https://blog.test/lasagne']),
            'blog.test/*' => Http::response('Lasagne'),
        ]);

        $this->assertSame('Lasagne', $this->fetcher()->get('https://short.test/abc')->body());
    }

    public function test_it_refuses_redirects_into_the_network(): void
    {
        Http::fake([
            'short.test/*' => Http::response(status: 302, headers: ['Location' => 'http://nas.home.test/admin']),
            'nas.home.test/*' => Http::response('secret'),
        ]);

        $this->assertRefused(fn () => $this->fetcher()->get('https://short.test/abc'));
        Http::assertSentCount(1);
    }

    public function test_it_gives_up_after_too_many_redirects(): void
    {
        Http::fake(['loop.test/*' => Http::response(status: 302, headers: ['Location' => '/again'])]);

        $this->assertRefused(fn () => $this->fetcher()->get('https://loop.test/start'));
    }

    private function fetcher(): PublicFetcher
    {
        return app(PublicFetcher::class);
    }

    private function assertRefused(callable $fetch): void
    {
        try {
            $fetch();
        } catch (ImportFailedException $exception) {
            $this->assertSame(ImportError::DownloadFailed, $exception->error);

            return;
        }

        $this->fail('The link was fetched.');
    }
}
