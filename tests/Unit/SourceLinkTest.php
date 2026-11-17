<?php

namespace Tests\Unit;

use App\Enums\SourcePlatform;
use App\Support\SourceLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceLinkTest extends TestCase
{
    #[DataProvider('urls')]
    public function test_it_detects_platform_and_video_id(?string $url, ?SourcePlatform $platform, ?string $videoId): void
    {
        $link = SourceLink::fromUrl($url);

        $this->assertSame($platform, $link->platform);
        $this->assertSame($videoId, $link->videoId);
    }

    /**
     * @return array<string, array{?string, ?SourcePlatform, ?string}>
     */
    public static function urls(): array
    {
        return [
            'tiktok video' => ['https://www.tiktok.com/@koch/video/7668426929069903137?_r=1', SourcePlatform::Tiktok, '7668426929069903137'],
            'tiktok short link' => ['https://vm.tiktok.com/ZNabc123/', SourcePlatform::Tiktok, null],
            'instagram reel' => ['https://www.instagram.com/reel/DAbc-12_x/?igsh=abc', SourcePlatform::Instagram, 'DAbc-12_x'],
            'instagram post with user' => ['https://www.instagram.com/koch/p/Cxyz987/', SourcePlatform::Instagram, 'Cxyz987'],
            'youtube short' => ['https://www.youtube.com/shorts/abcDEF12345', SourcePlatform::Youtube, 'abcDEF12345'],
            'youtube watch' => ['https://www.youtube.com/watch?feature=share&v=abcDEF12345', SourcePlatform::Youtube, 'abcDEF12345'],
            'youtube short host' => ['https://youtu.be/abcDEF12345', SourcePlatform::Youtube, 'abcDEF12345'],
            'website' => ['https://www.chefkoch.de/rezepte/123/lasagne.html', SourcePlatform::Web, null],
            'missing' => [null, null, null],
        ];
    }
}
