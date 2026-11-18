<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportSettings;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ImportSettingsController extends Controller
{
    private const string COOKIE_FILE_HEADER = '# Netscape HTTP Cookie File';

    public function __construct(private readonly ImportSettings $settings) {}

    /**
     * Show how links are fetched. The cookies themselves are never returned.
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'identify_as_browser' => $this->settings->identifiesAsBrowser(),
            'has_cookies' => $this->settings->hasUploadedCookies(),
        ]]);
    }

    /**
     * Switch fetching web pages as a browser on or off.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identify_as_browser' => ['required', 'boolean'],
        ]);

        $this->settings->identifyAsBrowser($data['identify_as_browser']);

        return $this->show();
    }

    /**
     * Store the cookie file of a signed-in browser for the video downloader.
     */
    public function storeCookies(Request $request): JsonResponse
    {
        $request->validate([
            'cookies' => ['required', 'file', 'max:1024', $this->isNetscapeCookieFile(...)],
        ]);

        $this->settings->storeCookies($request->file('cookies'));

        return $this->show();
    }

    /**
     * Remove the stored cookie file.
     */
    public function destroyCookies(): JsonResponse
    {
        $this->settings->deleteCookies();

        return $this->show();
    }

    /**
     * Validation rule: the file must be a cookie export in Netscape format.
     */
    private function isNetscapeCookieFile(string $attribute, mixed $file, Closure $fail): void
    {
        if (! $file instanceof UploadedFile || ! str_contains($file->getContent(), self::COOKIE_FILE_HEADER)) {
            $fail(__('messages.invalid_cookie_file'));
        }
    }
}
