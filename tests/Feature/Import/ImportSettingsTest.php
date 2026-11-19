<?php

namespace Tests\Feature\Import;

use App\Models\User;
use App\Services\Import\ImportSettings;
use App\Services\Import\VideoDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string COOKIES = "# Netscape HTTP Cookie File\n.instagram.com\tTRUE\t/\tTRUE\t0\tsessionid\tabc\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_the_importer_identifies_itself_unless_told_otherwise(): void
    {
        $settings = app(ImportSettings::class);

        $this->getJson('/api/v1/settings/import')
            ->assertOk()
            ->assertExactJson(['data' => ['identify_as_browser' => false, 'has_cookies' => false]]);
        $this->assertStringContainsString('DinnertimeBot', $settings->userAgent());

        $this->patchJson('/api/v1/settings/import', ['identify_as_browser' => true])
            ->assertJsonPath('data.identify_as_browser', true);
        $this->assertStringContainsString('Safari', $settings->userAgent());
        $this->assertStringNotContainsString('DinnertimeBot', $settings->userAgent());

        $this->patchJson('/api/v1/settings/import', ['identify_as_browser' => false])
            ->assertJsonPath('data.identify_as_browser', false);
    }

    public function test_uploaded_cookies_are_handed_to_the_video_downloader(): void
    {
        Process::fake(['*' => Process::result('{"id": "1"}')]);

        $this->postJson('/api/v1/settings/import/cookies', ['cookies' => $this->cookieFile(self::COOKIES)])
            ->assertOk()
            ->assertExactJson(['data' => ['identify_as_browser' => false, 'has_cookies' => true]]);

        app(VideoDownloader::class)->metadata('https://www.instagram.com/reel/abc/');

        Process::assertRan(fn (PendingProcess $process) => in_array('--cookies', $process->command)
            && in_array(Storage::disk('local')->path('import/cookies.txt'), $process->command));

        $this->deleteJson('/api/v1/settings/import/cookies')->assertJsonPath('data.has_cookies', false);
        $this->assertEmpty(app(ImportSettings::class)->cookiesFile());
    }

    public function test_it_rejects_files_that_are_not_cookie_exports(): void
    {
        $this->postJson('/api/v1/settings/import/cookies', ['cookies' => $this->cookieFile('sessionid=abc')])
            ->assertUnprocessable();

        $this->assertFalse(app(ImportSettings::class)->hasUploadedCookies());
    }

    private function cookieFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cookies.txt', $content);
    }
}
