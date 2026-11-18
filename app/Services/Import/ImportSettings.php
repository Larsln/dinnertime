<?php

namespace App\Services\Import;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * How the household wants links to be fetched.
 *
 * Some sites turn away requests that identify as an importer, and some
 * platforms only serve videos to signed-in users. Both workarounds are
 * off unless a household member switches them on.
 */
class ImportSettings
{
    private const string BROWSER_KEY = 'import.identify_as_browser';

    private const string COOKIES_PATH = 'import/cookies.txt';

    private const string IMPORTER_USER_AGENT = 'Mozilla/5.0 (compatible; MealplannerBot/1.0)';

    private const string BROWSER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

    /**
     * Whether web pages are fetched with the user agent of a browser.
     */
    public function identifiesAsBrowser(): bool
    {
        return (bool) Setting::find(self::BROWSER_KEY)?->value;
    }

    public function identifyAsBrowser(bool $enabled): void
    {
        Setting::updateOrCreate(['key' => self::BROWSER_KEY], ['value' => $enabled]);
    }

    /**
     * User agent sent when fetching web pages.
     */
    public function userAgent(): string
    {
        return $this->identifiesAsBrowser() ? self::BROWSER_USER_AGENT : self::IMPORTER_USER_AGENT;
    }

    /**
     * Path of the cookie file handed to the video downloader: the one the
     * household uploaded, otherwise the one configured for the instance.
     */
    public function cookiesFile(): ?string
    {
        return $this->hasUploadedCookies()
            ? Storage::disk('local')->path(self::COOKIES_PATH)
            : config('services.ytdlp.cookies');
    }

    public function hasUploadedCookies(): bool
    {
        return Storage::disk('local')->exists(self::COOKIES_PATH);
    }

    /**
     * Keep a cookie file in Netscape format exported from a browser that
     * is signed in to the platform.
     */
    public function storeCookies(UploadedFile $file): void
    {
        Storage::disk('local')->put(self::COOKIES_PATH, $file->getContent());
    }

    public function deleteCookies(): void
    {
        Storage::disk('local')->delete(self::COOKIES_PATH);
    }
}
