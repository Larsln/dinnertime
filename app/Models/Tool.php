<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kitchen tool or cooking appliance.
 */
#[Fillable(['name'])]
class Tool extends Model {}
