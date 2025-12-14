<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ReportController extends Controller {
  private $techList;
  private $prompts;
  private $parsingTemplate;
  private $titleGeneratorTemplate;
  private $onlyTitleTemplate;
  private $longSummaryTemplate;
  private $shortSummaryTemplate;
  private $summaryGeneratorTemplate;
  private $themeContent;

  public function __construct() {
    $this->themeContent = config("themeContent.themeContent");
    $this->techList = json_encode(config("techlist.techlist"));
    $this->prompts = config("prompts.prompts");
    $this->summaryGeneratorTemplate = <<<EOT
      Based on the resume data below, generate 1 concise and professional summary line.
      Each line should be a separate sentence highlighting key strengths, skills, or career objectives.
      Make it personal and engaging, representing the individual's professional identity.

      Eg 1: Enthusiastic and results-driven web developer passionate about building innovative and scalable web applications using modern technologies like React.js, Node.js, and the MERN stack.
      Eg 2 : Craving to build innovative solutions that make an impact. Enthusiastic problem solver, always curious about new technologies. Committed to continuous learning and growth.

      Resume data:
      {resume_data}

      Return ONLY valid JSON in this format without any explanations:
      {{
        "summaryLines": string[]
      }}
    >>>
    $this->shortSummaryTemplate = <<<EOT
      Based on the resume data below, generate a single short professional summary line (maximum 15-20 words).
      This should be a concise tagline that captures the person's professional identity and key strength.

      Examples:
      - "I build exceptional and accessible digital experiences for the web."
      - "Crafting innovative mobile solutions with cutting-edge technology."
      - "Transforming ideas into scalable software solutions."
      - "Building intelligent systems that solve real-world problems."

      Resume data:
      {resume_data}

      Return ONLY valid JSON in this format without any explanations:
      {{
        "shortSummary": string
      }}
    >>>
    $this->longSummaryTemplate = <<<EOT
      Based on the resume data below, generate a comprehensive professional summary paragraph (60-90 words).
      Include their role, years of experience, key technologies, specializations, educational background, interests, and career philosophy.
      Make it personal, engaging, and unique to their background. Avoid generic statements.

      Structure should flow naturally and include:
      - Professional identity and experience level
      - Key technical skills and specializations
      - Educational background or career journey
      - Personal interests or side projects
      - Career philosophy or goals

      Resume data:
      {resume_data}

      Return ONLY valid JSON in this format without any explanations:
      {{
        "longSummary": string
      }}
    EOT;
    $this->onlyTitleTemplate = <<<EOT
      Based on the resume data below, generate a single professional title that best represents the person's role and expertise.
      The title should be concise but comprehensive, combining their main expertise area with their role.

      Examples:
      - "Full Stack Developer"
      - "Frontend Engineer"
      - "Machine Learning Engineer"
      - "DevOps Specialist"
      - "UI/UX Designer"

      Resume data:
      {resume_data}

      Return ONLY valid JSON in this format without any explanations:
      {{
        "title": string
      }}
    EOT;
    $this->parsingTemplate = <<<EOT
        You are a professional resume parser. Given an image of a resume, extract the relevant information into a structured JSON format.
        Pay attention to all sections: personal information, summary, experience, education, skills, projects, and certifications.
        For dates, use MM/YYYY format when possible.

        For tech stack item refer to the provided tech list. $this->techList This list contains most of the possible tech stacks. 
        If the project uses any of these tech stacks then use the same name and image present in this array else use name present in resume. 
        Handle slightly different names like a resume may contain React and the array may contain React.js so use what is there in the array with its image.
        If image not present use any dummy image.

        For description projects, experience or any other kind of description summarize it in 3-4 lines at max. A user may have explained about the project in 10-12 lines so summarize it in around 3-4 lines.

        For education section, if description is not available, generate a 1-2 line description based on the degree name. For example:
        - For Computer Science: "Focused on software development, algorithms, and data structures. Gained hands-on experience in programming and system design."
        - For Business Administration: "Studied core business principles, management strategies, and market analysis. Developed strong leadership and analytical skills."
        - For Engineering: "Specialized in technical problem-solving and project management. Acquired practical knowledge in core engineering principles."

        Return ONLY valid JSON, without any markdown code blocks, backticks, or explanatory text. The response should be directly parseable as JSON.

        Use this exact schema:
        {{
          "personalInfo": {{
            "name": string,
            "email": string,
            "phone": string,
            "linkedin": string,
            "github": string (optional),
            "website": string (optional),
            "location": string (optional)
          }},
          "summary": string (optional),
          "experience": [
            {{
              "role": string,
              "companyName": string,
              "location": string (optional),
              "startDate": string,
              "endDate": string,
              "description": string,
              "techStack": [
                {{
                  "name": string,
                  "logo": string
                }}
              ]
            }}
          ],
          "education": [
            {{
              "degree": string,
              "institution": string,
              "location": string ,
              "startDate": string ,
              "endDate": string ,
              "description": string 
            }}
          ],
          "skills": [
            {{
              "name": string,
              "logo": string
            }}
          ],
          "projects": [
            {{
              "projectName": string,
              "projectTitle": string (optional),
              "projectDescription": string,
              "githubLink": string (optional),
              "liveLink": string (optional),
              "techStack": [
                {{
                  "name": string,
                  "logo": string
                }}
              ]
            }}
          ],
        }}

        Resume content:
        {resume_content}
        EOT;

        $this->titleGeneratorTemplate = <<<EOT
            Based on the resume data below, generate a professional title prefix and title suffix options.
            Extract the most prominent expertise area for the title prefix (e.g., "Frontend", "Full Stack", "Machine Learning").
            Generate 2-3 suffix options (e.g., "Engineer", "Developer", "Architect").

            Resume data:
            {resume_data}

            Return ONLY valid JSON in this format without any explanations:
            {{
              "titlePrefix": string,
              "titleSuffixOptions": string[]
            }}
        EOT;
  }

  public function extract(Request $request) {
    $base64 = $request->input("base64");
    $selectedTheme = $request->input("selectedTheme");

    $filePart = $this->fileToGeminiExpectedFormat($base64);
    $extractionPrompt = "Extract all text content from this resume image.";
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'x-goog-api-key' => env('GEMINI_API_KEY')
    ])->post(
      'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent',
      [
        'contents' => [
          [
            'parts' => [
              ['text' => $extractionPrompt],
              $filePart
            ]
          ]
        ]
      ]
    );

    $responseData = $response->json();
    $resumeContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    $formattedPrompt = str_replace('{resume_content}', $resumeContent, $this->parsingTemplate);
    $parsingResponse = Http::timeout(120)->withHeaders([
      'Content-Type' => 'application/json',
      'x-goog-api-key' => env('GEMINI_API_KEY')
    ])->post(
      'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent',
      [
        'contents' => [
          [
            'parts' => [
              ['text' => $formattedPrompt],
            ]
          ]
        ],
        'generationConfig' => [
          'temperature' => 0.1,
          'maxOutputTokens' => 8192,
          'responseMimeType' => 'application/json'
        ]
      ]
    );

    logger("parsing response: " . $parsingResponse);
    $parsingData = $parsingResponse->json();
    $parsedText = $parsingData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    $clean = $this->cleanJsonOutput($parsedText);

    if ($clean === null) {
      logger("Cleaned JSON is null — invalid model output");
      return response()->json([
        "message" => "Model returned invalid JSON",
      ], 500);
    }

    $resumeData = json_decode($clean, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      logger("JSON error (from parsingTemplate): " . json_last_error_msg());
      return response()->json([
        "message" => "Something went wrong" 
      ], 500);
    }

    $themePrompts = $this->prompts[$selectedTheme];

    $titleInfo = null;
    $summaryInfo = null;
    $shortSummaryInfo = null;
    $longSummaryInfo = null;
    
    if (!empty($themePrompts['titlePrefixSuffix'])) {

      $titleInfo = $this->safeAIGeneration(
          $this->titleGeneratorTemplate,
          json_encode($resumeData),
          0.7,
          500
      );

      if (!$titleInfo) {
          $titleInfo = [
              "titlePrefix" => "Software",
              "titleSuffixOptions" => ["Engineer", "Developer", "Architect"]
          ];
      }

    } elseif (!empty($themePrompts['title'])) {

      $titleInfo = $this->safeAIGeneration(
          $this->onlyTitleTemplate,
          json_encode($resumeData),
          0.7,
          300
      );

      if (!$titleInfo) {
          $titleInfo = [
              "title" => $resumeData['experience'][0]['role'] ?? "Software Developer"
          ];
      }
    }

    if (!empty($themePrompts['summaryPrompt'])) {

      $summaryInfo = $this->safeAIGeneration(
          $this->summaryGeneratorTemplate,
          json_encode($resumeData),
          0.7,
          500
      );

      if (!$summaryInfo) {
          $summaryInfo = [
              "summaryLines" => [
                  "Passionate developer focused on creating innovative solutions.",
                  "Enthusiastic about learning new technologies and best practices.",
                  "Committed to delivering high-quality, scalable applications."
              ]
          ];
      }
  }

  if (!empty($themePrompts['shortSummaryPrompt'])) {

      $shortSummaryInfo = $this->safeAIGeneration(
          $this->shortSummaryTemplate,
          json_encode($resumeData),
          0.7,
          300
      );

      if (!$shortSummaryInfo) {
          $shortSummaryInfo = [
              "shortSummary" =>
                  "Building exceptional digital experiences with modern technology."
          ];
      }
  }

    if (!empty($themePrompts['longSummaryPrompt'])) {

      $longSummaryInfo = $this->safeAIGeneration(
          $this->longSummaryTemplate,
          json_encode($resumeData),
          0.7,
          800
      );

      if (!$longSummaryInfo) {
          $skills = collect($resumeData['skills'] ?? [])
              ->take(3)
              ->pluck('name')
              ->implode(', ') ?: "various technologies";

          $longSummaryInfo = [
            "longSummary" =>
            "I'm a passionate developer with experience in $skills. I focus on creating intuitive user experiences and building scalable solutions. My journey in tech has been driven by continuous learning and adapting to new technologies."
          ];
      }
          
    }    

    $updatedResumeData = $this->mapTechStackWithTechList($resumeData);
    logger("Updated Resume Data: ",$updatedResumeData);

     
    $portfolioData = $this->convertToPortfolioFormat(
      $updatedResumeData,
      $titleInfo,
      $summaryInfo,
      $shortSummaryInfo,
      $longSummaryInfo,
      $themePrompts,
      $selectedTheme
    );

    logger("Final Portfolio Data: ", $portfolioData);

    return response()->json($portfolioData, 200);
  }

  private function convertToPortfolioFormat(
    $resumeData,
    $titleInfo,
    $summaryInfo,
    $shortSummaryInfo,
    $longSummaryInfo,
    $themePrompts,
    $selectedTheme
  ) {
    $sections = [];

    $sections[] = [
      "type" => "theme",
      "data" => $this->themeContent[$selectedTheme]
    ];

    if($resumeData["personalInfo"]) {
      $userInfoData = [
        "github" => $resumeData["personalInfo"]["github"] ?? '',
        "linkedin" => $resumeData["personalInfo"]["linkedin"] ?? '',
        "email" => $resumeData["personalInfo"]["email"] ?? '',
        "location" => $resumeData["personalInfo"]["location"] ?? '',
        "resumeLink" => $resumeData["personalInfo"]["resumeLink"] ?? '',
        "name" => $resumeData["personalInfo"]["name"] ?? "Alex Morgan",
      ];

      if(!empty($themePrompts["titlePrefixSuffix"])) {
        $prefix = $titleInfo["titlePrefix"] ?? "Software";
        $suffix = $titleInfo["titleSuffixOptions"][0] ?? "Engineer";

        $userInfoData["title"] = "$prefix $suffix";
      }
      else if(!empty($themePrompts["title"])) {
        $userInfoData["title"] = $titleInfo["title"] ?? "Software Developer";
      }
      else {
        $userInfoData["title"] = $resumeData["experience"][0]["role"] ?? "Software Developer";
      }

      $sections[] = [
        "type" => "userInfo",
        "data" => $userInfoData
      ];
    }

    $name = $resumeData["personalInfo"]["name"] ?? "Developer";

    $summaryLines = '';
    if(!empty($summaryInfo["summaryLines"]) && count($summaryInfo["summaryLines"]) > 0) {
      $summaryLines = implode("\n", $summaryInfo["summaryLines"]);
    }
    else if(!empty($resumeData["summary"])) {
      $lines = explode(". ", $resumeData["summary"]);
      $summaryLines = implode(".\n", array_slice($lines, 0, 3));
    }
    else {
      $skillNames = [];
      if(!empty($resumeData["skills"])) {
        $skillNames = array_map(fn($s) => $s["name"], $resumeData["skills"]);
        $skillNames = array_slice($skillNames, 0, 3);
      }

      $primarySkill = $skillNames[0] ?? "Software";
      $summaryLines = 
              "Passionate {$primarySkill} developer.\n" .
              "Enthusiastic about creating innovative solutions.\n" .
              "Dedicated to continuous learning and growth.";

    }

    $heroData = [
      "name" => $name,
      "summary" => $summaryLines
    ];

    if (!empty($themePrompts['titlePrefixSuffix'])) {

    $heroData["titlePrefix"] = $titleInfo['titlePrefix'] ?? "Software";
    $heroData["titleSuffixOptions"] = $titleInfo['titleSuffixOptions'] ?? ["Engineer", "Developer"];

    } elseif (!empty($themePrompts['title'])) {

      $heroData["title"] = $titleInfo['title'] ?? "Software Developer";
    }

    if (!empty($themePrompts['shortSummaryPrompt'])) {

      $shortSummary = "I build exceptional and accessible digital experiences for the web.";

      if (!empty($shortSummaryInfo['shortSummary'])) {

          $shortSummary = $shortSummaryInfo['shortSummary'];

      } elseif (!empty($resumeData['summary'])) {

          $firstSentence = explode(".", $resumeData['summary'])[0] . ".";

          if (strlen($firstSentence) <= 100) {
              $shortSummary = $firstSentence;
          }
      }

      $heroData["shortSummary"] = $shortSummary;
    }

    if (!empty($themePrompts['longSummaryPrompt'])) {

      $longSummary =
          "I'm a passionate Full Stack Developer with 4+ years of experience building modern web applications. " .
          "I specialize in React, Node.js, and cloud technologies, with a strong focus on creating intuitive user " .
          "experiences and scalable backend systems. My journey in tech started during my Computer Science studies, " .
          "and I've been continuously learning and adapting to new technologies ever since. When I'm not coding, " .
          "you'll find me contributing to open-source projects, writing technical blogs, or exploring the latest in " .
          "AI and machine learning. I believe in the power of technology to solve real-world problems and am always " .
          "excited to take on new challenges.";

      if (!empty($longSummaryInfo['longSummary'])) {
          $longSummary = $longSummaryInfo['longSummary'];
      }

      $heroData["longSummary"] = $longSummary;
    }

    if (!empty($themePrompts['badge'])) {
      $heroData["badge"] = [
          "texts" => ["Open to work", "Available for freelance", "Let's Collaborate!"],
          "color" => "green",
          "isVisible" => true,
      ];
    }   


    if (!empty($themePrompts['actions'])) {
      $heroData["actions"] = [
          [
              "type" => "button",
              "label" => "View Projects",
              "url" => "#projects",
              "style" => "primary",
          ],
          [
              "type" => "button",
              "label" => "Contact Me",
              "url" => "#contact",
              "style" => "outline",
          ],
      ];
    }

    $sections[] = [
        "type" => "hero",
        "data" => $heroData
    ];

    if (!empty($resumeData['projects']) && is_array($resumeData['projects'])) {

        $formattedProjects = array_map(function ($project) {
            return [
                'projectName' => $project['projectName'] ?? '',
                'projectTitle' => $project['projectTitle']
                    ?? implode(" ", array_slice(explode(" ", $project['projectName'] ?? ''), 0, 3)),
                'projectDescription' => $project['projectDescription'] ?? '',
                'githubLink' => $project['githubLink'] ?? "https://github.com/user/project",
                'liveLink' => $project['liveLink'] ?? "https://project-demo.vercel.app",
                'projectImage' => "https://placehold.co/600x400?text=Project+Image",
                'techStack' => $project['techStack'] ?? [],
            ];
        }, $resumeData['projects']);

        $sections[] = [
            'type' => 'projects',
            'data' => $formattedProjects,
        ];
    }

    if (!empty($resumeData['experience']) && is_array($resumeData['experience'])) {

        $formattedExperience = array_map(function ($exp) {
            return [
                'role' => $exp['role'] ?? '',
                'companyName' => $exp['companyName'] ?? '',
                'location' => $exp['location'] ?? 'Remote',
                'startDate' => $exp['startDate'] ?? '01/2023',
                'endDate' => $exp['endDate'] ?? 'Present',
                'description' => $exp['description'] ?? '',
                'techStack' => $exp['techStack'] ?? [],
            ];
        }, $resumeData['experience']);

        $sections[] = [
            'type' => 'experience',
            'data' => $formattedExperience,
        ];
    }

    if (!empty($resumeData['skills']) && is_array($resumeData['skills'])) {
        $sections[] = [
            'type' => 'technologies',
            'data' => $resumeData['skills'],
        ];
    }

    if (!empty($resumeData['education']) && is_array($resumeData['education'])) {
        $sections[] = [
            'type' => 'education',
            'data' => $resumeData['education'],
        ];
    }

    return ['sections' => $sections];

  } 


  private function mapTechStackWithTechList($resumeData) {
    $techMap = [];

    foreach(json_decode($this->techList, true) as $tech) {
      $normalizeName = $this->normalizeString($tech['name']);
      $techMap[$normalizeName] = $tech;
    }

    if (!empty($resumeData['skills']) && is_array($resumeData['skills'])) {
        $resumeData['skills'] = $this->updateTechStack(
            $resumeData['skills'],
            $techMap
        );
    }

    if (!empty($resumeData['experience']) && is_array($resumeData['experience'])) {
        foreach ($resumeData['experience'] as &$exp) {
            if (!empty($exp['techStack'])) {
                $exp['techStack'] = $this->updateTechStack(
                    $exp['techStack'],
                    $techMap
                );
            }
        }
    }

    if (!empty($resumeData['projects']) && is_array($resumeData['projects'])) {
        foreach ($resumeData['projects'] as &$project) {
            if (!empty($project['techStack'])) {
                $project['techStack'] = $this->updateTechStack(
                    $project['techStack'],
                    $techMap
                );
            }
        }
    }

    return $resumeData;
  }

  private function updateTechStack(?array $items, array $techMap): array {
    if (!$items) return [];

    $updated = [];

    foreach ($items as $item) {
      if (empty($item['name'])) continue;

      $match = $this->findBestMatch($item['name'], $techMap);

      if ($match) {
        $updated[] = [
          'name' => $item['name'],
          'logo' => $match['logo']
        ];
      }
    }

    return $updated;
  }

  private function findBestMatch(string $techName, $techMap) {
    $normalized = $this->normalizeString($techName);

    if(isset($techMap[$normalized])) {
      return $techMap[$normalized];
    }

    foreach ($techMap as $key => $tech) {
      if(str_contains($key, $normalized) || str_contains($normalized, $key)) {
        return $tech;
      }
    }

    $specialCases = [
      'js' => 'javascript',
      'ts' => 'typescript',
      'reactjs' => 'react',
      'nextjs' => 'next.js',
      'expressjs' => 'express.js',
      'nodejs' => 'node.js',
      'tailwind' => 'tailwindcss',
      'postgres' => 'postgresql',
      'gemini' => 'google gemini',
      'langchainjs' => 'langchain',
      'langchain js' => 'langchain',
      'shadcnui' => 'shadcn ui',
    ];

    if (isset($specialCases[$normalized])) {
      $mapped = $this->normalizeString($specialCases[$normalized]);
      return $techMap[$mapped] ?? null;
    }

    return null;
  }

  private function normalizeString(string $str): string {
    $str = strtolower($str);
    $str = preg_replace('/[.\s-]/', '', $str);
    $str = preg_replace('/\.js$/', '', $str);
    $str = preg_replace(['/^react$/', '/^vue$/', '/^angular$/'], ['reactjs', 'vuejs', 'angularjs'], $str);

    return $str; 
  }

  private function safeAIGeneration($template, $data, $temperature = 0.7, $maxTokens = 500) {
    try {
      $formattedPrompt = str_replace('{resume_data}', $data, $template);
      $response = Http::timeout(30)->withHeaders([
        'Content-Type' => 'application/json',
        'x-goog-api-key' => env('GEMINI_API_KEY')  
      ])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent',
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $formattedPrompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]
        );

      if($response->failed()) {
        logger("Gemini Failed: ", $response->json());
        return null;
      }

      logger("Genmini respone: " . $response);
      $responseData = $response->json();
      $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

      $clean = $this->cleanJsonOutput($responseText);

      logger("Clean Genmini respone: " . $clean);

      $parsed = json_decode($clean, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
          logger("JSON error: (from $template)" . json_last_error_msg());
          return null;
      }

      return $parsed;
    } 
    catch(\Exception $e) {
      logger("AI generation error: " . $e->getMessage());
      return null;
    }
  }

  private function fileToGeminiExpectedFormat(string $base64) {
    return [
      "inlineData" => [
        "data" => explode(',', $base64)[1],
        "mimeType" => substr($base64, strpos($base64, ':') + 1, strpos($base64, ';') - strpos($base64, ':') - 1)
      ]
    ];
  }

  private function cleanJsonOutput(string $text) {
    if (empty($text) || !is_string($text)) {
        logger("Empty or non-string response from model");
        return null;
    }

    $cleaned = trim($text);

    $cleaned = preg_replace('/```(json)?/i', '', $cleaned);
    $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleaned);

    $first = strpos($cleaned, '{');
    $last  = strrpos($cleaned, '}');

    if ($first !== false && $last !== false) {
      $cleaned = substr($cleaned, $first, $last - $first + 1);
    }

    if (empty(trim($cleaned))) {
        return null;
    }    

    return $cleaned;
  }
}
