<?php

namespace App\Support;

/**
 * Line icons a product can be pictured with on the shopping list.
 *
 * The keys name icons of the open-source Tabler icon set, which both
 * clients bundle.
 */
final class ProductIcons
{
    /**
     * Icon for products nothing else fits.
     */
    public const string FALLBACK = 'basket';

    public const array KEYS = [
        'apple', 'banana', 'cherry', 'grape', 'lemon', 'melon', 'avocado',
        'carrot', 'pepper', 'mushroom', 'salad', 'leaf', 'plant',
        'bread', 'baguette', 'cookie', 'cake', 'chocolate', 'candy', 'lollipop',
        'milk', 'cheese', 'egg', 'eggs', 'ice-cream',
        'meat', 'sausage', 'fish',
        'grain', 'wheat', 'bowl', 'soup', 'salt', 'nut', 'bottle',
        'pizza', 'burger',
        'coffee', 'cup', 'glass', 'beer', 'milkshake', 'droplet',
        'snowflake', 'paper-bag',
        'toilet-paper', 'wash', 'spray', 'trash', 'baby-bottle', 'diaper', 'paw', 'pill', 'bandage', 'battery', 'bulb',
        self::FALLBACK,
    ];
}
