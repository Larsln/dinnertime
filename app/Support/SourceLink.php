<?php

namespace App\Support;

use App\Enums\SourcePlatform;

/**
 * Platform and video id extracted from the URL a recipe was imported from.
 *
 * The video id identifies a video independently of the share link, which
 * differs on every share for some platforms.
 */
final readonly class SourceLink
{
    private const array VIDEO_ID_PATTERNS = [
        SourcePlatform::Tiktok->value => '~tiktok\.com/.*/video/(\d+)~',
        SourcePlatform::Instagram->value => '~instagram\.com/(?:[^/]+/)?(?:reels?|p|tv)/([\w-]+)~',
        SourcePlatform::Youtube->value => '~(?:youtu\.be/|youtube\.com/(?:shorts/|watch\?(?:.*&)?v=))([\w-]+)~',
    ];

    private const array HOSTS = [
        SourcePlatform::Tiktok->value => 'tiktok.com',
        SourcePlatform::Instagram->value => 'instagram.com',
        SourcePlatform::Youtube->value => 'youtu',
    ];

    public function __construct(
        public ?SourcePlatform $platform,
        public ?string $videoId,
    ) {}

    /**
     * Detect the platform and, where the URL contains it, the video id.
     */
    public static function fromUrl(?string $url): self
    {
        if (blank($url)) {
            return new self(null, null);
        }

        foreach (self::HOSTS as $platform => $host) {
            if (str_contains((string) parse_url($url, PHP_URL_HOST), $host)) {
                preg_match(self::VIDEO_ID_PATTERNS[$platform], $url, $matches);

                return new self(SourcePlatform::from($platform), $matches[1] ?? null);
            }
        }

        return new self(SourcePlatform::Web, null);
    }
}
