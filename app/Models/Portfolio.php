<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $table = "portfolio";
    
    protected $fillable = [
        "is_template", 
		"user_id", 
		"content",
		"is_published",
		"template_name",
		"font_name", 
		"theme_name" 
    ];
}
