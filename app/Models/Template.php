<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = "template";
    protected $keyType = "string";
	public $incrementing = false;

	protected $casts = [
        'id' => 'string',
    ];
}
