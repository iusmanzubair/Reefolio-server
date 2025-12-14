<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
  public function fetchThemes() {
  	try {
      $themes = Template::get();

      $order = [
        "NeoSpark" => 1,
        "LumenFlow" => 2,
        "MonoEdge" => 3
      ];

      $orderedThemes = $themes->sortBy(function ($theme) use ($order) {
        return $order[$theme->name] ?? 999; 
      })->values();

      Log::info($orderedThemes->toArray());

      return response()->json([
      	"data" => $orderedThemes
      ], 200);
    }  catch(\Throwable $e) {
        Log::error("Error Fetching Theme: ", [
          "error" => $e->getMessage()
        ]);

        return response()->json([
          "message" => "Error fetching Themes"
        ], 500);
    }
  }
}