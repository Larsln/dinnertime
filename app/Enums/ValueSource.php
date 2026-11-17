<?php

namespace App\Enums;

/**
 * Origin of a product conversion or package size.
 */
enum ValueSource: string
{
    case Ai = 'ai';
    case User = 'user';
    case Import = 'import';
}
