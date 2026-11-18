<?php

namespace App\Enums;

/**
 * What a running import is doing, shown to the user as progress.
 */
enum ImportStage: string
{
    case Downloading = 'downloading';
    case Analyzing = 'analyzing';
    case Saving = 'saving';
}
