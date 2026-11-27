<?php

namespace App\Enums;

/**
 * How the portion of a meal was for someone who ate it.
 */
enum PortionVerdict: string
{
    case TooSmall = 'too_small';
    case Right = 'right';
    case TooBig = 'too_big';
}
