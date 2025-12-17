<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Template;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortfolioController extends Controller
{
	public function createPortfolio(Request $request) {
    try {
			$userId = $request->input("userId");	
			$templateName = $request->input("templateName");	
			$customBodyResume = $request->input("customBodyResume");	

			Logger($userId);
			Logger($templateName);
			Logger($customBodyResume);

			$template = Template::where("name", $templateName)->select("default_content")->first();
			if(!$template || !$template->default_content) {
				return response()->json([
					"message" => "Template not found"
				], 404);
			}
			Log::info($template->default_content);

			$content = null;
			if($customBodyResume) {
				$templateContent = is_string($template->default_content) ? json_decode($template->default_content, true) : $template->default_content;
				$customContent = $customBodyResume;
				$customSectionMap = [];

				if(isset($customContent["sections"]) && is_array($customContent["sections"])) {
					foreach($customContent["sections"] as $section) {
						if(isset($section["type"])) {
							$customSectionMap[$section["type"]] = $section;
						}
					}
				}

				$newContent = $templateContent;
				$newContent["sections"] = array_map(function ($section) use ($customSectionMap) {
					$customSection = $customSectionMap[$section["type"]] ?? null;

					if($customSection) {
						$mergedData = json_decode(json_encode($section["data"]), true);
						foreach($customSection["data"] as $key => $customValue) {
							$templateValue = $section["data"][$key] ?? null;
							
							if(is_array($customValue)) {
								$mergedData[$key] = $customValue;
							}
							else if(is_array($templateValue) && is_array($customValue)) {
								$mergedData[$key] = array_map(function ($item, $index) use ($customValue) {
									return array_merge($item, $customValue[$index]) ?? [];
								}, $templateValue, array_keys($templateValue));
							}
							else if(is_array($customValue)) {
								$mergedData[$key] = array_merge($templateValue ?? [], $customValue);
							}
							else {
								$mergedData[$key] = $customValue;
							}
						}

						Log::info("section: ", $section);

						return array_merge($section, [
							"data" => $mergedData,
							"sectionTitle" => isset($section["sectionTitle"]) ? $section["sectionTitle"] : "",
							"sectionDescription" => isset($section["sectionDescription"]) ? $section["sectionDescription"] : ""
						]);
					}

					return $section;
				}, $templateContent["sections"]);

				$content = $newContent;
			}
			else {
				$content = $template->default_content;
			}

			Log::info("Content: ", $content);

			$maps = [
				"NeoSpark" => [
					"themeName" => "SparklyGreen",
					"fontName" => "Raleway"
				],
				"MonoEdge" => [
					"themeName" => "simpleBlack",
					"fontName" => "Raleway"
				],
				"LumenFlow" => [
					"themeName" => "SunsetOcean",
					"fontName" => "Raleway"
				],
			];

			$newTemplate = Portfolio::create([
				"is_template" => false,
				"user_id" => $userId,
				"content" => json_encode($content),
				"is_published" => false,
				"template_name" => $templateName,
				"font_name" => $maps[$templateName]["fontName"] ?? null,
				"theme_name" => $maps[$templateName]["themeName"] ?? null 
			]);

			return response()->json([
				"data" => $newTemplate
			], 200);
    }
    catch (\Throwable $e) {
      Log::error("Failed to create portfolio", [
				"error" => $e->getMessage()
			]);

			return response()->json([
				"message" => "Failed to create portfolio"
			], 500);
    }
  }

	public function fetchPortfolio(Request $request) {
		try {
			$portfolioId = $request->query("pid");
			$portfolio = Portfolio::where("id", $portfolioId)->first();
			
			if(!$portfolio) {
				return response()->json([
					"message" => "Portfolio not found"
				], 404);
			}

			Log::info($portfolio);

			return response()->json([
				"data" => $portfolio->content
			], 200);
		}
		catch (\Throwable $e) {
			Log::error("Error fetching portfolio: ", [
				"error" => $e->getMessage()
			]);
		}
	}

	public function fetchPortfoliosByUserId(Request $request) {
		try {
			$authHeader = $request->header("Authorization");
			$token = str_replace('Bearer ', '', $authHeader);

			$secret = env("SUPABASE_JWT_SECRET");
			$decoded = JWT::decode($token, new Key($secret, 'HS256'));
			
			$userId = $decoded->sub;
			$portfolios = Portfolio::with("template")->where("user_id", $userId)->get();

			return response()->json([
				"portfolios" => $portfolios,
			], 200);
		}
		catch (\Throwable $e) {
			Log::error("Error fetching portfolios: ", [
				"error" => $e->getMessage()
			]);

			return response()->json([
				"message" => "Error fetching portfolios"
			], 404);
		}
	}
}
