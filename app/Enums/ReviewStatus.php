<?php

namespace App\Enums;

/**
 * Review state of a value that may have been proposed by the AI.
 */
enum ReviewStatus: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
}
