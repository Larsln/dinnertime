<?php

namespace App\Enums;

/**
 * Meal of the day a plan entry is scheduled for.
 *
 * The values match the keys of the meal tags recipes are labelled with.
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Dessert = 'dessert';
    case Snack = 'snack';
}
