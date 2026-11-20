<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

/**
 * Reads metadata and downloads videos with yt-dlp.
 *
 * Platforms sporadically reject requests, so every call is attempted
 * several times with a growing pause.
 */
class VideoDownloader
{
    /**
     * 720p is enough for the AI and keeps uploads small. Both sides are
     * limited, because platforms report the long side of portrait videos as
     * their height. Platforms that only offer separate video and audio
     * streams get them merged.
     */
    private const string FORMAT = 'best[ext=mp4][height<=1280][width<=1280]'
        .'/bestvideo[height<=1280][width<=1280]+bestaudio'
        .'/best[height<=1280][width<=1280]/best';

    private const int ATTEMPTS = 3;

    private const int BACKOFF_SECONDS = 3;

    private const int TIMEOUT_SECONDS = 180;

    /**
     * Only the extractors of known sites, which only reach their own
     * domains. The generic one would fetch any address, including those
     * inside the home network.
     */
    private const string EXTRACTORS = 'all,-generic';

    public function __construct(private readonly ImportSettings $settings) {}

    /**
     * Metadata of the video behind a link, including its id and caption.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function metadata(string $url): array
    {
        return json_decode($this->run(['--dump-single-json', $url]), true);
    }

    /**
     * Download the video into the directory and return the file's path.
     *
     * @throws ImportFailedException
     */
    public function download(string $url, string $directory): string
    {
        $this->run([
            '--format', self::FORMAT,
            '--merge-output-format', 'mp4',
            '--output', "{$directory}/video.%(ext)s",
            $url,
        ]);

        return glob("{$directory}/video.*")[0]
            ?? throw new ImportFailedException(ImportError::DownloadFailed, 'yt-dlp wrote no file');
    }

    /**
     * @param  list<string>  $arguments
     *
     * @throws ImportFailedException
     */
    private function run(array $arguments): string
    {
        $command = [
            'yt-dlp',
            '--no-playlist',
            '--no-warnings',
            '--use-extractors', self::EXTRACTORS,
            ...(filled($this->settings->cookiesFile()) ? ['--cookies', $this->settings->cookiesFile()] : []),
            ...$arguments,
        ];

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run($command);

            if ($result->successful()) {
                return $result->output();
            }

            if ($attempt < self::ATTEMPTS) {
                Sleep::for(self::BACKOFF_SECONDS * $attempt)->seconds();
            }
        }

        throw new ImportFailedException(ImportError::DownloadFailed, $result->errorOutput());
    }
}
