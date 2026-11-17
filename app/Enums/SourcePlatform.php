<?php

namespace App\Enums;

/**
 * Platform a recipe was imported from.
 */
enum SourcePlatform: string
{
    case Tiktok = 'tiktok';
    case Instagram = 'instagram';
    case Youtube = 'youtube';
    case Web = 'web';
}
