<?php
/**
 * Sauvegarde d'une plante complète générée par l'IA
 * Enregistre la plante avec ses 3 recettes (germination, végétative, floraison)
 */

require_once __DIR__ . '/../../src/database/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Empêcher tout output non-JSON
ob_start();

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupération des données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['plant'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Données invalides - objet plant requis']);
    exit;
}

$plant = $input['plant'];

// Validation des champs requis
$requiredFields = ['variety', 'germination_recipe', 'vegetative_recipe', 'flowering_recipe'];
foreach ($requiredFields as $field) {
    if (!isset($plant[$field])) {
        ob_end_clean();
        echo json_encode(['error' => "Champ manquant: $field"]);
        exit;
    }
}

/**
 * Sauvegarde une recette et ses nutriments
 * @return int ID de la recette créée
 */
function saveRecipe($pdo, $recipe) {
    $recipeName = $recipe['name'] ?? 'Recette sans nom';
    $waterQuantity = isset($recipe['water_quantity']) ? (int)$recipe['water_quantity'] : 0;
    $duration = isset($recipe['duration']) ? (int)$recipe['duration'] : 0;
    $nutrients = $recipe['nutrients'] ?? [];
    
    // Validation des nutriments
    if (empty($nutrients)) {
        throw new Exception("La recette '$recipeName' n'a pas de nutriments");
    }
    
    $totalPercentage = 0;
    foreach ($nutrients as $nutrient) {
        $totalPercentage += (int)($nutrient['percentage'] ?? 0);
    }
    
    if ($totalPercentage !== 100) {
        throw new Exception("Les nutriments de '$recipeName' doivent totaliser 100% (actuellement: $totalPercentage%)");
    }
    
    // 1. Insérer la recette
    $stmtRecipe = $pdo->prepare("INSERT INTO RECIPE (recipe_name, water_quantity, duration, creation_date) VALUES (:name, :water, :duration, NOW())");
    $stmtRecipe->execute([
        ':name' => $recipeName,
        ':water' => $waterQuantity,
        ':duration' => $duration
    ]);
    
    $recipeId = $pdo->lastInsertId();
    
    // 2. Pour chaque nutriment
    foreach ($nutrients as $nutrient) {
        $nutrientName = trim($nutrient['name']);
        $percentage = (int)$nutrient['percentage'];
        
        // Vérifier si le nutriment existe déjà
        $stmtCheck = $pdo->prepare("SELECT id_nutrient FROM NUTRIENT WHERE nutrient_name = :name");
        $stmtCheck->execute([':name' => $nutrientName]);
        $nutrientId = $stmtCheck->fetchColumn();
        
        // Si le nutriment n'existe pas, le créer
        if (!$nutrientId) {
            $stmtInsertNutrient = $pdo->prepare("INSERT INTO NUTRIENT (nutrient_name, creation_date) VALUES (:name, NOW())");
            $stmtInsertNutrient->execute([':name' => $nutrientName]);
            $nutrientId = $pdo->lastInsertId();
        }
        
        // Lier le nutriment à la recette avec son pourcentage
        $stmtPercentage = $pdo->prepare("INSERT INTO PERCENTAGE (percentage, id_nutrient, id_recipe, creation_date) VALUES (:pct, :nid, :rid, NOW())");
        $stmtPercentage->execute([
            ':pct' => $percentage,
            ':nid' => $nutrientId,
            ':rid' => $recipeId,
        ]);
    }
    
    return $recipeId;
}

try {
    $pdo->beginTransaction();
    
    // 1. Sauvegarder les 3 recettes
    $germinationRecipeId = saveRecipe($pdo, $plant['germination_recipe']);
    $vegetativeRecipeId = saveRecipe($pdo, $plant['vegetative_recipe']);
    $floweringRecipeId = saveRecipe($pdo, $plant['flowering_recipe']);
    
    // 2. Récupérer ou créer le groupe si spécifié
    $groupId = null;
    if (isset($plant['id_group']) && !empty($plant['id_group'])) {
        $groupId = (int)$plant['id_group'];
        
        // Vérifier que le groupe existe
        $stmtCheckGroup = $pdo->prepare("SELECT id_group FROM GROUP_TABLE WHERE id_group = :id");
        $stmtCheckGroup->execute([':id' => $groupId]);
        if (!$stmtCheckGroup->fetchColumn()) {
            $groupId = null; // Groupe non trouvé, on ignore
        }
    }
    
    // 3. Insérer la plante
    $variety = trim($plant['variety']);
    
    $stmtPlant = $pdo->prepare("
        INSERT INTO PLANT (variety, germination_recipe, vegetative_recipe, flowering_recipe, id_group, creation_date) 
        VALUES (:variety, :germination, :vegetative, :flowering, :group_id, NOW())
    ");
    $stmtPlant->execute([
        ':variety' => $variety,
        ':germination' => $germinationRecipeId,
        ':vegetative' => $vegetativeRecipeId,
        ':flowering' => $floweringRecipeId,
        ':group_id' => $groupId
    ]);
    
    $plantId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    ob_end_clean();
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => "Plante '$variety' créée avec succès avec ses 3 recettes !",
        'data' => [
            'plant_id' => $plantId,
            'variety' => $variety,
            'recipes' => [
                'germination' => [
                    'id' => $germinationRecipeId,
                    'name' => $plant['germination_recipe']['name']
                ],
                'vegetative' => [
                    'id' => $vegetativeRecipeId,
                    'name' => $plant['vegetative_recipe']['name']
                ],
                'flowering' => [
                    'id' => $floweringRecipeId,
                    'name' => $plant['flowering_recipe']['name']
                ]
            ],
            'id_group' => $groupId,
            'creation_date' => date('Y-m-d')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    echo json_encode(['error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()]);
}
?>
