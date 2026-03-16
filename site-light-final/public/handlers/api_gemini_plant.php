<?php
/**
 * API pour générer des plantes complètes avec Google Gemini AI
 * Génère une plante avec ses 3 recettes: germination, végétative (pousse), floraison
 * Utilise la sortie structurée JSON pour une meilleure cohérence
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Empêcher tout output non-JSON
ob_start();

// Configuration API Gemini
define('GOOGLE_API_KEY', 'AIzaSyC4cZ1XSR2InTQHA-94BAzOiMLhYKRWOi8');
define('MODEL', 'gemini-2.5-flash');

/**
 * Schéma JSON pour la sortie structurée d'une plante
 */
function getPlantSchema() {
    return [
        'type' => 'object',
        'properties' => [
            'variety' => [
                'type' => 'string',
                'description' => 'Nom de la variété de plante (ex: Tomate Cerise, Basilic Grand Vert)'
            ],
            'germination_recipe' => [
                'type' => 'object',
                'description' => 'Recette pour la phase de germination',
                'properties' => [
                    'name' => [
                        'type' => 'string', 'description' => 'Nom de la recette de germination'],
                    'water_quantity' => ['type' => 'integer', 'description' => 'Volume d\'eau en litres'],
                    'duration' => ['type' => 'integer', 'description' => 'Durée en jours'],
                    'nutrients' => [
                        'type' => 'array',
                        'description' => 'Liste des nutriments avec pourcentages (totalisant 100%)',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Nom du nutriment'],
                                'percentage' => ['type' => 'integer', 'description' => 'Pourcentage (0-100)']
                            ],
                            'required' => ['name', 'percentage']
                        ]
                    ]
                ],
                'required' => ['name', 'water_quantity', 'duration', 'nutrients']
            ],
            'vegetative_recipe' => [
                'type' => 'object',
                'description' => 'Recette pour la phase végétative (pousse)',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Nom de la recette végétative'],
                    'water_quantity' => ['type' => 'integer', 'description' => 'Volume d\'eau en litres'],
                    'duration' => ['type' => 'integer', 'description' => 'Durée en jours'],
                    'nutrients' => [
                        'type' => 'array',
                        'description' => 'Liste des nutriments avec pourcentages (totalisant 100%)',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Nom du nutriment'],
                                'percentage' => ['type' => 'integer', 'description' => 'Pourcentage (0-100)']
                            ],
                            'required' => ['name', 'percentage']
                        ]
                    ]
                ],
                'required' => ['name', 'water_quantity', 'duration', 'nutrients']
            ],
            'flowering_recipe' => [
                'type' => 'object',
                'description' => 'Recette pour la phase de floraison',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Nom de la recette de floraison'],
                    'water_quantity' => ['type' => 'integer', 'description' => 'Volume d\'eau en litres'],
                    'duration' => ['type' => 'integer', 'description' => 'Durée en jours'],
                    'nutrients' => [
                        'type' => 'array',
                        'description' => 'Liste des nutriments avec pourcentages (totalisant 100%)',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Nom du nutriment'],
                                'percentage' => ['type' => 'integer', 'description' => 'Pourcentage (0-100)']
                            ],
                            'required' => ['name', 'percentage']
                        ]
                    ]
                ],
                'required' => ['name', 'water_quantity', 'duration', 'nutrients']
            ]
        ],
        'required' => ['variety', 'germination_recipe', 'vegetative_recipe', 'flowering_recipe']
    ];
}

/**
 * Envoie une requête à l'API Gemini avec sortie structurée JSON
 */
function callGeminiAPIWithSchema($prompt, $schema) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . MODEL . ':generateContent?key=' . GOOGLE_API_KEY;
    
    $requestBody = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 1.0,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema
        ]
    ];
    
    $jsonPayload = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => 'Erreur CURL: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        // Vérifier si c'est du HTML
        if (strpos($response, '<') === 0) {
            return ['error' => 'Erreur HTTP ' . $httpCode . ': Réponse HTML - Vérifiez l\'API key et l\'endpoint'];
        }
        
        // Essayer de parser comme JSON
        $errorData = json_decode($response, true);
        if ($errorData && isset($errorData['error'])) {
            if (is_array($errorData['error'])) {
                $errorMsg = $errorData['error']['message'] ?? json_encode($errorData['error']);
            } else {
                $errorMsg = $errorData['error'];
            }
            return ['error' => 'API Error: ' . $errorMsg];
        }
        
        return ['error' => 'Erreur HTTP ' . $httpCode . ': ' . substr($response, 0, 300)];
    }
    
    // Parser la réponse JSON
    $result = json_decode($response, true);
    if (!$result) {
        return ['error' => 'Erreur: Réponse invalide - ' . json_last_error_msg()];
    }
    
    return $result;
}

/**
 * Extrait et parse la réponse JSON structurée de Gemini
 */
function extractPlantResponse($geminiResponse) {
    if (isset($geminiResponse['error'])) {
        return $geminiResponse;
    }
    
    if (isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
        $plantData = json_decode($jsonText, true);
        
        if ($plantData === null) {
            return ['error' => 'Erreur de parsing JSON: ' . json_last_error_msg()];
        }
        
        // Validation des données
        $validation = validatePlantData($plantData);
        if ($validation !== true) {
            return ['error' => $validation];
        }
        
        return ['success' => true, 'plant' => $plantData];
    }
    
    return ['error' => 'Format de réponse inattendu'];
}

/**
 * Valide les données de la plante générée
 */
function validatePlantData($plant) {
    $requiredFields = ['variety', 'germination_recipe', 'vegetative_recipe', 'flowering_recipe'];
    
    foreach ($requiredFields as $field) {
        if (!isset($plant[$field])) {
            return "Champ manquant: $field";
        }
    }
    
    $recipes = ['germination_recipe', 'vegetative_recipe', 'flowering_recipe'];
    
    foreach ($recipes as $recipeKey) {
        $recipe = $plant[$recipeKey];
        
        if (!isset($recipe['name']) || !isset($recipe['nutrients']) || !is_array($recipe['nutrients'])) {
            return "Format de recette invalide: $recipeKey";
        }
        
        // Vérifier que les nutriments totalisent 100%
        $totalPercentage = 0;
        foreach ($recipe['nutrients'] as $nutrient) {
            if (!isset($nutrient['name']) || !isset($nutrient['percentage'])) {
                return "Format de nutriment invalide dans: $recipeKey";
            }
            $totalPercentage += $nutrient['percentage'];
        }
        
        if ($totalPercentage !== 100) {
            return "Les nutriments de $recipeKey doivent totaliser 100% (actuellement: $totalPercentage%)";
        }
    }
    
    return true;
}

// Traitement de la requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['variety']) || empty(trim($input['variety']))) {
        ob_end_clean();
        echo json_encode(['error' => 'Veuillez fournir une variété de plante']);
        exit;
    }
    
    $variety = trim($input['variety']);
    $groupId = isset($input['id_group']) ? (int)$input['id_group'] : null;
    
    // Prompt système pour générer une plante complète
    $systemPrompt = "Tu es un expert en agriculture hydroponique et nutrition des plantes.

Génère les recettes de nutriments complètes pour cultiver: {$variety}

RÈGLES STRICTES:
1. Génère une plante avec EXACTEMENT 3 recettes:
   - germination_recipe: Phase de germination/semis (durée typique: 7-14 jours)
   - vegetative_recipe: Phase végétative/pousse (durée typique: 21-42 jours)
   - flowering_recipe: Phase de floraison/fructification (durée typique: 30-60 jours)

2. Pour CHAQUE recette, fournis:
   - name: Nom descriptif (ex: '{$variety} - Germination')
   - water_quantity: Volume d'eau en litres (5-50L selon la phase)
   - duration: Durée en jours
   - nutrients: EXACTEMENT 5 nutriments dont les pourcentages totalisent 100%

3. Nutriments obligatoires pour chaque recette:
   - Azote (N): Plus élevé en phase végétative
   - Phosphore (P): Plus élevé en germination et floraison
   - Potassium (K): Plus élevé en floraison
   - Calcium (Ca): Constant, important pour la structure
   - Magnésium (Mg): Constant, important pour la chlorophylle

4. Adapte les ratios NPK selon la phase:
   - Germination: N moyen, P élevé, K faible (racines et premières feuilles)
   - Végétative: N élevé, P moyen, K moyen (croissance feuillage)
   - Floraison: N faible, P élevé, K élevé (fruits et fleurs)

5. Réponds UNIQUEMENT avec le JSON structuré demandé.";

    $plantSchema = getPlantSchema();
    $geminiResponse = callGeminiAPIWithSchema($systemPrompt, $plantSchema);
    $result = extractPlantResponse($geminiResponse);
    
    // Ajouter les métadonnées
    if (isset($result['success']) && $result['success']) {
        $result['plant']['id_group'] = $groupId;
        $result['plant']['creation_date'] = date('Y-m-d');
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Méthode GET pour test
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    
    if (isset($_GET['test'])) {
        echo json_encode([
            'status' => 'API active',
            'endpoint' => 'api_gemini_plant.php',
            'method' => 'POST',
            'params' => [
                'variety' => '(required) Nom de la variété de plante',
                'id_group' => '(optional) ID du groupe'
            ],
            'response_schema' => getPlantSchema()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    if (isset($_GET['quick-test'])) {
        // Test rapide de l'API Gemini
        $testSchema = [
            'type' => 'object',
            'properties' => [
                'response' => ['type' => 'string']
            ]
        ];
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . MODEL . ':generateContent?key=' . GOOGLE_API_KEY;
        
        $testData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Respond with: {"response": "success"}']
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 1.0,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 100,
                'responseMimeType' => 'application/json',
                'responseSchema' => $testSchema
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo json_encode([
            'http_code' => $httpCode,
            'curl_error' => $error,
            'response_preview' => substr($response, 0, 500),
            'is_html' => strpos($response, '<') === 0,
            'parsed_json' => json_decode($response, true)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

ob_end_clean();
echo json_encode(['error' => 'Méthode non autorisée. Utilisez POST.']);
?>
