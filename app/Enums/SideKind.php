<?php

namespace App\Enums;

/**
 * What a side dish is based on. A side is of one kind, and a dish names
 * the kinds it is served with.
 */
enum SideKind: string
{
    case Potato = 'potato';
    case Rice = 'rice';
    case Pasta = 'pasta';
    case Bread = 'bread';
    case Vegetable = 'vegetable';
    case Salad = 'salad';

    /**
     * Name in the current locale.
     */
    public function label(): string
    {
        return __("reference.side_kinds.{$this->value}");
    }
}
