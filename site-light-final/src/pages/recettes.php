<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recettes - Optiplant</title>
    <!-- Inclusion des icônes FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inclusion du style commun du menu -->
    <link rel="stylesheet" href="/css/style_menu.css">
    <link rel="stylesheet" href="/css/style_recettes.css">
</head>
<body>

<?php 
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}
require_once __DIR__ . '/../database/db.php';
include __DIR__ . '/menu.php';

// Récupération des recettes existantes
$recipes = [];
$availableNutrients = [];
$successMsg = "";
$errorMsg = "";

// Afficher messages de redirection du handler
if (isset($_GET['success'])) {
    $successMsg = "Recette sauvegardée avec succès!";
}
if (isset($_GET['error'])) {
    $errorMsg = htmlspecialchars($_GET['error']);
}

try {
    // 1. Récupérer les nutriments disponibles pour le formulaire
    $stmtNut = $pdo->query("SELECT * FROM NUTRIENT ORDER BY nutrient_name ASC");
    $availableNutrients = $stmtNut->fetchAll(PDO::FETCH_ASSOC);

    // 2. On récupère toutes les recettes triées par date décroissante
    $stmt = $pdo->query("SELECT * FROM RECIPE ORDER BY creation_date DESC, id_recipe DESC");
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pour chaque recette, on va chercher ses nutriments
    foreach ($recipes as &$recipe) {
        $stmtNutrients = $pdo->prepare("
            SELECT n.nutrient_name, p.percentage 
            FROM PERCENTAGE p
            JOIN NUTRIENT n ON p.id_nutrient = n.id_nutrient
            WHERE p.id_recipe = ?
        ");
        $stmtNutrients->execute([$recipe['id_recipe']]);
        $recipe['nutrients'] = $stmtNutrients->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Si la table n'existe pas encore ou erreur
    if (!$errorMsg) { // Ne pas écraser le message du handler
        $errorMsg = "Impossible de charger les données : " . $e->getMessage();
    }
}
?>

<div class="container">
    <h1 class="header-main">RECETTES</h1>
    
    <!-- Section IA - Génération de plante complète -->
    <div class="ai-section plant-section">
        <div class="sub-header"><i class="fas fa-seedling"></i> Créer une plante complète avec l'IA</div>
        <p class="ai-description">Entrez le nom d'une variété de plante pour générer automatiquement les 3 recettes (germination, végétative, floraison).</p>
        
        <div class="ai-input-group">
            <input type="text" id="plantVariety" placeholder="Ex: Tomate Cerise, Basilic, Laitue..." class="ai-input">
            <button type="button" id="generatePlantBtn" onclick="generatePlant()" class="btn-ai btn-plant">
                <i class="fas fa-leaf"></i> Créer la plante
            </button>
        </div>
        
        <div id="plantLoading" class="ai-loading" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i> Génération des 3 recettes en cours...
        </div>
        
        <div id="plantResult" class="plant-result" style="display: none;">
            <h4><i class="fas fa-check-circle"></i> Plante générée : <span id="plantVarietyName"></span></h4>
            
            <div class="recipes-grid">
                <!-- Germination -->
                <div class="recipe-phase germination">
                    <div class="phase-header">
                        <i class="fas fa-spa"></i> Germination
                    </div>
                    <div class="phase-content">
                        <div class="phase-row"><span>Nom :</span> <span id="germName"></span></div>
                        <div class="phase-row"><span>Durée :</span> <span id="germDuration"></span> jours</div>
                        <div class="phase-row"><span>Volume :</span> <span id="germWater"></span> L</div>
                        <div class="phase-nutrients" id="germNutrients"></div>
                    </div>
                </div>
                
                <!-- Végétative -->
                <div class="recipe-phase vegetative">
                    <div class="phase-header">
                        <i class="fas fa-leaf"></i> Végétative
                    </div>
                    <div class="phase-content">
                        <div class="phase-row"><span>Nom :</span> <span id="vegName"></span></div>
                        <div class="phase-row"><span>Durée :</span> <span id="vegDuration"></span> jours</div>
                        <div class="phase-row"><span>Volume :</span> <span id="vegWater"></span> L</div>
                        <div class="phase-nutrients" id="vegNutrients"></div>
                    </div>
                </div>
                
                <!-- Floraison -->
                <div class="recipe-phase flowering">
                    <div class="phase-header">
                        <i class="fas fa-sun"></i> Floraison
                    </div>
                    <div class="phase-content">
                        <div class="phase-row"><span>Nom :</span> <span id="flowerName"></span></div>
                        <div class="phase-row"><span>Durée :</span> <span id="flowerDuration"></span> jours</div>
                        <div class="phase-row"><span>Volume :</span> <span id="flowerWater"></span> L</div>
                        <div class="phase-nutrients" id="flowerNutrients"></div>
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="savePlant()" class="btn-save-ai btn-save-plant">
                <i class="fas fa-save"></i> Enregistrer cette plante et ses recettes
            </button>
        </div>
        
        <div id="plantError" class="ai-error" style="display: none;"></div>
        <div id="plantSuccess" class="ai-success" style="display: none;"></div>
    </div>
    

    <hr style="margin: 30px 0; border: none; border-top: 2px dashed #ddd;">
    
    <div class="sub-header">Ajouter une recette manuellement</div>

    <form action="/handlers/sauvegarder_recette.php" method="POST">
        
        <div class="labels-row">
            <div style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 15px; max-width: 350px;">
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="color: #000; font-weight: bold;">Nom de la recette :</label>
                    <input type="text" name="recipeName" required 
                           style="border: 2px solid #000; padding: 10px; border-radius: 8px; outline: none;">
                </div>

                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="color: #000; font-weight: bold;">Volume d'eau (Litres) :</label>
                    <input type="number" name="waterVolume" step="0.1" min="0" required
                           style="border: 2px solid #000; padding: 10px; border-radius: 8px; outline: none;">
                </div>
            </div>

            <div class="dropdown">
                <button type="button" onclick="toggleDropdown()" class="dropbtn" id="mainButton">Choisir les nutriments</button>
                <div id="myDropdown" class="dropdown-content">
                    
                    <?php if (!empty($availableNutrients)): ?>
                        <?php foreach ($availableNutrients as $nut): ?>
                            <label class="option-row">
                                <input type="checkbox" name="nutriments[]" value="<?php echo htmlspecialchars($nut['nutrient_name']); ?>" class="option-checkbox">
                                <span><?php echo htmlspecialchars($nut['nutrient_name']); ?></span>
                                <input type="number" name="valeurs[<?php echo htmlspecialchars($nut['nutrient_name']); ?>]" class="percent-input" placeholder="%" min="0" max="100">
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div id="addZone" style="padding: 10px; border-top: 1px solid #ccc;">
                        <input type="text" id="newItemInput" placeholder="Nouveau nutriment" style="width: 70%;">
                        <button type="button" onclick="addNewElement()">+</button>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="submit_recette" class="btn-valider">Valider</button>
    </form>
</div>

<script src="/js/script_recettes.js"></script>
    
    <!-- Zone d'historique -->
    <div class="history-section">
        <h2 class="history-title">Historique des recettes</h2>
        
        <?php if (!empty($successMsg)): ?>
            <div class="success" style="background-color: #d4edda; border: 1px solid #28a745; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMsg)): ?>
            <div class="error" style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorMsg; ?>
            </div>
        <?php elseif (!empty($recipes)): ?>
            <?php foreach ($recipes as $r): ?>
                <div class="recipe-card">
                    <div class="recipe-header">
                        <span class="recipe-name"><?php echo htmlspecialchars($r['recipe_name']); ?></span>
                        <span class="recipe-date"><?php echo date('d/m/Y', strtotime($r['creation_date'])); ?></span>
                    </div>
                    
                    <div style="margin-bottom: 15px; font-size: 0.95em;">
                        <strong>Volume d'eau :</strong> <?php echo htmlspecialchars($r['water_quantity']); ?> Litres
                    </div>
                    
                    <?php if (isset($r['nutrients']) && !empty($r['nutrients'])): ?>
                        <div class="nutrient-list">
                            <?php foreach ($r['nutrients'] as $n): ?>
                                <span class="nutrient-tag">
                                    <?php echo htmlspecialchars($n['nutrient_name']); ?> 
                                    (<?php echo htmlspecialchars($n['percentage']); ?>%)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.85em; color: #7f8c8d; font-style: italic;">Aucun nutriment associé</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-recipes">Aucune recette enregistrée pour le moment.</div>
        <?php endif; ?>
    </div>
</div>

<script>
// Variables globales pour stocker la recette générée
let currentAiRecipe = null;

// Générer une recette avec l'IA...etc.
</script>

</body>
</html>