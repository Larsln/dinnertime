<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Process;

/**
 * Extracts still images from a video with ffmpeg.
 */
class FrameExtractor
{
    /**
     * Seconds searched before and after the requested moment.
     */
    private const float WINDOW = 0.6;

    private const int TIMEOUT_SECONDS = 60;

    /**
     * Save the sharpest frame around a moment of the video as a JPEG.
     * Returns false when no frame could be extracted.
     */
    public function sharpestFrame(string $video, float $second, string $target): bool
    {
        $start = max(0, $second - self::WINDOW);

        $analysis = Process::timeout(self::TIMEOUT_SECONDS)->run([
            'ffmpeg', '-hide_banner',
            '-ss', (string) $start,
            '-t', (string) (2 * self::WINDOW),
            '-i', $video,
            '-vf', 'blurdetect,metadata=print:file=-',
            '-an', '-f', 'null', '-',
        ]);

        $extraction = Process::timeout(self::TIMEOUT_SECONDS)->run([
            'ffmpeg', '-hide_banner', '-y',
            '-ss', (string) ($start + $this->leastBlurredOffset($analysis->output())),
            '-i', $video,
            '-frames:v', '1',
            '-q:v', '2',
            $target,
        ]);

        return $extraction->successful() && is_file($target);
    }

    /**
     * Offset in seconds of the frame with the lowest blur value in the
     * metadata ffmpeg printed, or zero when there is none.
     */
    private function leastBlurredOffset(string $metadata): float
    {
        preg_match_all('/pts_time:([\d.]+)\s+lavfi\.blur=([\d.]+)/', $metadata, $matches, PREG_SET_ORDER);

        $sharpest = collect($matches)->sortBy(fn (array $match) => (float) $match[2])->first();

        return (float) ($sharpest[1] ?? 0);
    }
}
