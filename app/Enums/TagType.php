<?php

namespace App\Enums;

/**
 * Fixed vocabularies a recipe can be labelled with.
 */
enum TagType: string
{
    case Meal = 'meal';
    case Tag = 'tag';
}
