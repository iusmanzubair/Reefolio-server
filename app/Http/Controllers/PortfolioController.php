<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
	public function createPortfolio(Request $request) {
    try {
			$userId = $request->input("userId");	
			$templateName = $request->input("templateName");	
			$customBodyResume = $request->input("customBodyResume");	

			$template = Template::where("name", $templateName)->select("defaultContent")->first();
			if(!$template || !$template->defaultContent) {
				return response()->json([
					"message" => "Template not found"
				], 404);
			}

			$content = null;
			if($customBodyResume) {
				$templateContent = is_string($template->defaultContent) ? json_decode($template->defaultContent, true) : $template->defaultContent;
				$customContent = json_decode($customBodyResume, true);
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
						$mergeData = json_decode(json_encode($section["data"]), true);
						foreach($customSection["data"] as $key => $customValue) {
							$templateValue = $section["data"][$key] ?? null;
							
							if(is_array($customValue)) {
								$mergeData[$key] = $customValue;
							}
							else if(is_array($templateValue) && is_array($customValue)) {
								$mergeData[$key] = array_map(function ($item, $index) use ($customValue) {
									return array_merge($item, $customValue[$index]) ?? [];
								}, $templateValue, array_keys($templateValue));
							}
						}
					}
				}, $templateContent["sections"]);
			}
    }
    catch (\Throwable $e) {
            
    }
  }
}
