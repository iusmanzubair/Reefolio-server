<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $table = "portfolio";
		protected $keyType = "string";
		public $incrementing = false;

		protected $casts = [
        'id' => 'string',
    ];
    
    protected $fillable = [
      "is_template", 
			"user_id", 
			"content",
			"is_published",
			"template_name",
			"font_name", 
			"theme_name" 
    ];

		public function template() {
    	return $this->belongsTo(Template::class, 'template_name', 'name');
		}
}
