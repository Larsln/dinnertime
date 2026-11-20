<?php

namespace App\Services\Import;

use App\Models\Recipe;
use App\Services\Recipes\CoverStore;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Gives recipes from videos their cover image.
 *
 * The cover is the sharpest frame around the moments the AI suggested,
 * the one it likes best among them. When no frame can be taken, the
 * preview image of the platform stands in. A recipe is worth more than its
 * cover, so nothing that goes wrong here fails an import.
 */
class VideoCoverMaker
{
    private const int MAX_CANDIDATES = 4;

    /**
     * Seconds kept clear of the end of the video, where no frame may follow.
     */
    private const float END_MARGIN = 0.5;

    public function __construct(
        private readonly FrameExtractor $frames,
        private readonly CoverPicker $picker,
        private readonly CoverStore $covers,
        private readonly ImageDownloader $images,
    ) {}

    /**
     * Store the best frame of the video as cover, or else the preview image.
     *
     * @param  list<float|int>  $seconds  Moments the AI suggested.
     */
    public function fromVideo(Recipe $recipe, string $video, array $seconds, ?float $duration, ?string $previewUrl, string $directory): void
    {
        try {
            $candidates = $this->frameCandidates($recipe, $video, $seconds, $duration, $directory);

            if ($candidates !== []) {
                $this->covers->put($recipe, File::get($candidates[$this->picker->pick($candidates)]), 'jpg');

                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->fromPreview($recipe, $previewUrl);
    }

    /**
     * Store the preview image of the platform as cover. Returns whether
     * the recipe got one.
     */
    public function fromPreview(Recipe $recipe, ?string $previewUrl): bool
    {
        if (blank($previewUrl)) {
            return false;
        }

        try {
            $image = $this->images->download($previewUrl);

            if ($image === null) {
                return false;
            }

            $this->covers->put($recipe, $image['contents'], $image['extension']);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Frames that could be taken around the suggested moments. Moments
     * beyond the end of the video are moved to its end.
     *
     * @param  list<float|int>  $seconds
     * @return list<string>
     */
    private function frameCandidates(Recipe $recipe, string $video, array $seconds, ?float $duration, string $directory): array
    {
        $last = $duration === null ? null : max(0, $duration - self::END_MARGIN);
        $candidates = [];

        foreach (array_slice($seconds, 0, self::MAX_CANDIDATES) as $index => $second) {
            $path = "{$directory}/cover-{$recipe->id}-{$index}.jpg";
            $moment = $last === null ? (float) $second : min((float) $second, $last);

            if ($this->frames->sharpestFrame($video, max(0, $moment), $path)) {
                $candidates[] = $path;
            }
        }

        return $candidates;
    }
}
