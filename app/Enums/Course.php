<?php

namespace App\Enums;

/**
 * Main category of a dish. Every recipe belongs to at most one course.
 */
enum Course: string
{
    case Starter = 'starter';
    case Soup = 'soup';
    case Salad = 'salad';
    case Main = 'main';
    case Side = 'side';
    case Dessert = 'dessert';
    case Baking = 'baking';
    case Snack = 'snack';
    case Drink = 'drink';

    /**
     * Name in the current locale.
     */
    public function label(): string
    {
        return __("reference.courses.{$this->value}");
    }
}
