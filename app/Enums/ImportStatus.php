<?php

namespace App\Enums;

/**
 * Progress of a recipe import.
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
