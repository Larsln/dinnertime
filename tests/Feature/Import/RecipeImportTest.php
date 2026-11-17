<?php

namespace Tests\Feature\Import;

use App\Enums\ImportError;
use App\Enums\ImportStatus;
use App\Enums\ReviewStatus;
use App\Enums\SourcePlatform;
use App\Jobs\EnrichProduct;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class RecipeImportTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private const string VIDEO_URL = 'https://vm.tiktok.com/ZNabc123/';

    private const string RESOLVED_URL = 'https://www.tiktok.com/@koch/video/7668426929069903137';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sleep::fake();
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        config(['services.gemini.key' => 'secret', 'services.gemini.base_url' => 'https://gemini.test']);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_queues_an_import_and_reports_its_outcome(): void
    {
        Queue::fake();

        $id = $this->postJson('/api/v1/imports', ['url' => self::VIDEO_URL])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        Queue::assertPushedOn('imports', ImportRecipe::class);
        $this->getJson("/api/v1/imports/{$id}")->assertJsonPath('data.url', self::VIDEO_URL);
        $this->getJson('/api/v1/imports')->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/imports', ['url' => 'ftp://example.com/x'])->assertUnprocessable();
    }

    public function test_a_video_becomes_a_recipe_with_products_and_cover(): void
    {
        Queue::fake([EnrichProduct::class]);
        $soySauce = $this->product('Sojasoße');
        $soySauce->aliases()->create(['name' => 'Sojasauce']);
        $this->fakeVideoTools();
        $this->fakeGemini($this->draft());

        $import = $this->runImport(self::VIDEO_URL);

        $this->assertSame(ImportStatus::Completed, $import->status);
        $recipe = $import->recipe->load('ingredients.product', 'ingredients.unit', 'steps', 'tags', 'tools');
        $this->assertSame('Hot-Honey Chicken Tenders', $recipe->title);
        $this->assertSame(SourcePlatform::Tiktok, $recipe->source_platform);
        $this->assertSame('7668426929069903137', $recipe->source_video_id);
        $this->assertSame(self::RESOLVED_URL, $recipe->source_url);
        $this->assertFalse($recipe->meal_tags_locked);
        $this->assertEqualsCanonicalizing(['dinner', 'quick'], $recipe->tags->pluck('key')->all());
        $this->assertSame(['Airfryer'], $recipe->tools->pluck('name')->all());

        [$sauce, $chicken] = $recipe->ingredients;
        $this->assertTrue($sauce->product->is($soySauce));
        $this->assertSame('tablespoon', $sauce->unit->key);
        $this->assertSame('Soße', $sauce->section);
        $this->assertTrue($sauce->is_estimated);
        $this->assertSame('Hähnchenbrustfilet', $chicken->product->name);
        $this->assertSame(ReviewStatus::Suggested, $chicken->product->status);
        $this->assertNull($chicken->unit);

        Storage::disk('public')->assertExists($recipe->cover_image_path);
        Queue::assertPushed(EnrichProduct::class, 2);
        $this->assertSame([], File::directories(storage_path('app/imports')));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), ':generateContent')
            && isset($request['contents'][0]['parts'][0]['fileData'])
            && str_contains($request['contents'][0]['parts'][1]['text'], 'Rezept in der Caption'));
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_contains($request->url(), 'files/abc'));
    }

    public function test_a_video_imported_before_is_not_imported_again(): void
    {
        $existing = Recipe::factory()->create([
            'source_platform' => SourcePlatform::Tiktok,
            'source_video_id' => '7668426929069903137',
        ]);
        $this->fakeVideoTools();

        $this->postJson('/api/v1/imports', ['url' => self::RESOLVED_URL])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.recipe.id', $existing->id);

        $import = $this->runImport(self::VIDEO_URL);

        $this->assertTrue($import->recipe->is($existing));
        $this->assertSame(1, Recipe::count());
        Http::assertNothingSent();
    }

    public function test_it_explains_why_an_import_failed(): void
    {
        $this->fakeVideoTools();
        $this->fakeGemini([...$this->draft(), 'is_recipe' => false]);

        $import = $this->runImport(self::VIDEO_URL);

        $this->assertSame(ImportError::NoRecipeFound, $import->status === ImportStatus::Failed ? $import->error : null);
        $this->getJson("/api/v1/imports/{$import->id}", ['Accept-Language' => 'de'])
            ->assertJsonPath('data.error', 'no_recipe_found')
            ->assertJsonPath('data.error_message', 'Unter diesem Link wurde kein Rezept gefunden.');
        $this->assertSame(0, Recipe::count());
    }

    public function test_a_failed_download_is_retried_and_reported(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'HTTP Error 403', exitCode: 1)]);

        $import = $this->runImport(self::VIDEO_URL);

        $this->assertSame(ImportError::DownloadFailed, $import->error);
        Process::assertRanTimes(fn (PendingProcess $process) => in_array('--dump-single-json', $process->command), 3);

        Queue::fake();
        $this->postJson("/api/v1/imports/{$import->id}/retry")->assertAccepted()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/v1/imports/{$import->id}/retry")->assertConflict();
    }

    public function test_a_blocked_ai_answer_fails_the_import(): void
    {
        $this->fakeVideoTools();
        Http::fake([
            'gemini.test/upload/*' => Http::response(['file' => $this->geminiFile()], headers: ['X-Goog-Upload-URL' => 'https://gemini.test/upload/session']),
            'gemini.test/v1beta/models/*' => Http::response(['promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT']]),
            'gemini.test/*' => Http::response([]),
        ]);

        $this->assertSame(ImportError::AiFailed, $this->runImport(self::VIDEO_URL)->error);
    }

    public function test_a_web_page_becomes_a_recipe(): void
    {
        Queue::fake([EnrichProduct::class]);
        Http::fake([
            'blog.test/lasagne' => Http::response('<html><head><meta property="og:image" content="https://blog.test/cover.jpg">'
                .'<script type="application/ld+json">{"@type":"Recipe","name":"Lasagne"}</script><style>p{}</style></head>'
                .'<body><p>Die beste Lasagne</p></body></html>'),
            'blog.test/cover.jpg' => Http::response('jpeg-bytes', headers: ['Content-Type' => 'image/jpeg']),
            'gemini.test/*' => Http::response($this->geminiAnswer([...$this->draft(), 'cover_seconds' => []])),
        ]);

        $import = $this->runImport('https://blog.test/lasagne');

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(SourcePlatform::Web, $import->recipe->source_platform);
        $this->assertSame("covers/{$import->recipe->id}.jpg", $import->recipe->cover_image_path);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), ':generateContent')
            && str_contains($request['contents'][0]['parts'][0]['text'], '"@type":"Recipe"')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Die beste Lasagne')
            && ! str_contains($request['contents'][0]['parts'][0]['text'], 'p{}'));

        $this->assertTrue($this->runImport('https://blog.test/lasagne')->recipe->is($import->recipe));
    }

    public function test_without_an_ai_key_the_link_is_kept_as_an_empty_recipe(): void
    {
        config(['services.gemini.key' => null]);
        $this->app->forgetInstance(GeminiClient::class);

        $import = $this->runImport(self::RESOLVED_URL);

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame('www.tiktok.com', $import->recipe->title);
        $this->assertSame('7668426929069903137', $import->recipe->source_video_id);
        Http::assertNothingSent();
    }

    private function runImport(string $url): RecipeImport
    {
        $import = RecipeImport::create(['url' => $url]);

        ImportRecipe::dispatchSync($import);

        return $import->refresh();
    }

    /**
     * Fake yt-dlp and ffmpeg. The download writes a file where yt-dlp
     * would, frame extraction writes the requested image.
     */
    private function fakeVideoTools(): void
    {
        Process::fake(function (PendingProcess $process) {
            $command = $process->command;

            if (in_array('--dump-single-json', $command)) {
                return Process::result(json_encode([
                    'id' => '7668426929069903137',
                    'webpage_url' => self::RESOLVED_URL,
                    'description' => 'Rezept in der Caption #fyp',
                ]));
            }

            if ($command[0] === 'yt-dlp') {
                File::put(str_replace('%(ext)s', 'mp4', $command[array_search('--output', $command) + 1]), 'video-bytes');

                return Process::result();
            }

            if (in_array('-frames:v', $command)) {
                File::put(end($command), 'jpeg-bytes');

                return Process::result();
            }

            return Process::result("frame:0 pts:1 pts_time:0.1\nlavfi.blur=9.5\nframe:1 pts:2 pts_time:0.4\nlavfi.blur=2.5\n");
        });
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function fakeGemini(array $draft): void
    {
        Http::fake([
            'gemini.test/upload/v1beta/files' => Http::response([], headers: ['X-Goog-Upload-URL' => 'https://gemini.test/upload/session']),
            'gemini.test/upload/session' => Http::response(['file' => [...$this->geminiFile(), 'state' => 'PROCESSING']]),
            'gemini.test/v1beta/files/abc' => Http::response($this->geminiFile()),
            'gemini.test/v1beta/models/gemini-3.8-flash*' => Http::response($this->geminiAnswer($draft)),
            'gemini.test/v1beta/models/gemini-3.5-flash-lite*' => Http::response($this->geminiAnswer(['index' => 1])),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function geminiFile(): array
    {
        return ['name' => 'files/abc', 'uri' => 'https://gemini.test/files/abc', 'mimeType' => 'video/mp4', 'state' => 'ACTIVE'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function geminiAnswer(array $data): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($data)]]]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(): array
    {
        return [
            'is_recipe' => true,
            'title' => 'Hot-Honey Chicken Tenders',
            'description' => 'Knusprige Tenders mit scharfem Honig.',
            'servings' => 2,
            'yield_text' => null,
            'prep_minutes' => 15,
            'cook_minutes' => 12,
            'ingredients' => [
                ['section' => 'Soße', 'name' => 'sojasauce', 'quantity' => 2, 'unit' => 'tablespoon', 'note' => null, 'is_estimated' => true],
                ['section' => null, 'name' => 'Hähnchenbrustfilet', 'quantity' => 2, 'unit' => null, 'note' => 'in Streifen', 'is_estimated' => false],
            ],
            'steps' => [
                ['title' => 'Soße', 'text' => 'Sojasoße und Honig verrühren.'],
                ['title' => null, 'text' => 'Tenders 12 Minuten im Airfryer garen.'],
            ],
            'tools' => ['Airfryer'],
            'meals' => ['dinner'],
            'categories' => [],
            'tags' => ['quick'],
            'cover_seconds' => [3.5, 12],
        ];
    }
}
