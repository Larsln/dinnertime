<?php

namespace Tests\Feature\Import;

use App\Enums\SourcePlatform;
use App\Models\Recipe;
use App\Services\Import\VideoCoverMaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class VideoCoverTest extends TestCase
{
    use RefreshDatabase;

    private const string PREVIEW_URL = 'https://p16.tiktokcdn.test/cover.webp';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        config(['services.gemini.key' => 'secret', 'services.gemini.base_url' => 'https://gemini.test']);
        $this->directory = storage_path('framework/testing/covers');
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_preview_image_stands_in_when_no_frame_can_be_taken(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->fakePreview();
        $recipe = Recipe::factory()->create();

        $this->maker()->fromVideo($recipe, 'video.mp4', [3, 8], 10.0, self::PREVIEW_URL, $this->directory);

        $this->assertStringEndsWith('.webp', $recipe->refresh()->cover_image_path);
        Storage::disk('public')->assertExists($recipe->cover_image_path);
    }

    public function test_a_crashing_frame_extraction_does_not_fail_the_import(): void
    {
        Process::fake(fn (PendingProcess $process) => in_array('-t', $process->command, true)
            ? throw new ProcessTimedOutException(new SymfonyProcess(['ffmpeg']), ProcessTimedOutException::TYPE_GENERAL)
            : Process::result());
        $this->fakePreview();
        $recipe = Recipe::factory()->create();

        $this->maker()->fromVideo($recipe, 'video.mp4', [3], 10.0, self::PREVIEW_URL, $this->directory);

        $this->assertStringEndsWith('.webp', $recipe->refresh()->cover_image_path);
    }

    public function test_a_cover_is_kept_when_its_small_version_cannot_be_made(): void
    {
        Process::fake(fn () => throw new ProcessTimedOutException(new SymfonyProcess(['ffmpeg']), ProcessTimedOutException::TYPE_GENERAL));
        $this->fakePreview();
        $recipe = Recipe::factory()->create();

        $this->maker()->fromPreview($recipe, self::PREVIEW_URL);

        Storage::disk('public')->assertExists($recipe->refresh()->cover_image_path);
        $this->assertNull($recipe->cover_thumb_path);
    }

    public function test_moments_after_the_end_are_moved_to_the_end(): void
    {
        $this->fakeFrames();
        Http::fake(['gemini.test/*' => Http::response(status: 500)]);
        $recipe = Recipe::factory()->create();

        $this->maker()->fromVideo($recipe, 'video.mp4', [129, 4], 7.0, null, $this->directory);

        Process::assertRan(fn (PendingProcess $process) => in_array('-frames:v', $process->command, true)
            && $process->command[array_search('-ss', $process->command, true) + 1] === '5.9');
        $this->assertStringEndsWith('.jpg', $recipe->refresh()->cover_image_path);
    }

    public function test_without_frames_and_preview_the_recipe_stays_without_cover(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $recipe = Recipe::factory()->create();

        $this->maker()->fromVideo($recipe, 'video.mp4', [], null, null, $this->directory);

        $this->assertNull($recipe->refresh()->cover_image_path);
    }

    public function test_recipes_without_cover_get_the_preview_image_afterwards(): void
    {
        $this->fakePreview();
        Process::fake(['*' => Process::result(json_encode(['id' => '1', 'thumbnail' => self::PREVIEW_URL]))]);
        $video = Recipe::factory()->create(['source_platform' => SourcePlatform::Tiktok, 'source_url' => 'https://www.tiktok.com/@koch/video/1']);
        $web = Recipe::factory()->create(['source_platform' => SourcePlatform::Web, 'source_url' => 'https://blog.test/x']);

        $this->artisan('recipes:fetch-missing-covers')->assertSuccessful();

        $this->assertNotNull($video->refresh()->cover_image_path);
        $this->assertNull($web->refresh()->cover_image_path);
        Process::assertRanTimes(fn (PendingProcess $process) => $process->command[0] === 'yt-dlp', 1);
    }

    private function maker(): VideoCoverMaker
    {
        return app(VideoCoverMaker::class);
    }

    private function fakePreview(): void
    {
        Http::fake([self::PREVIEW_URL => Http::response('webp-bytes', headers: ['Content-Type' => 'image/webp'])]);
    }

    /**
     * Frame extraction writes the requested image; the blur analysis and
     * the thumbnail step report nothing.
     */
    private function fakeFrames(): void
    {
        Process::fake(function (PendingProcess $process) {
            if (in_array('-frames:v', $process->command, true)) {
                File::put(end($process->command), 'jpeg-bytes');
            }

            return Process::result();
        });
    }
}
