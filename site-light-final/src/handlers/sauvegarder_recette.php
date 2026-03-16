<?php
/**
 * Handler pour sauvegarder une nouvelle recette
 * POST data:
 *   - recipeName: nom de la recette
 *   - waterVolume: volume d'eau en litres
 *   - nutriments[]: array de noms de nutriments sélectionnés
 *   - valeurs[nutrient_name]: array de pourcentages par nutriment
 */

session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /recettes.php");
    exit();
}

require_once __DIR__ . '/../database/db.php';

try {
    // Récupérer et valider les données
    $recipeName = trim($_POST['recipeName'] ?? '');
    $waterVolume = (int)($_POST['waterVolume'] ?? 0);
    $nutriments = $_POST['nutriments'] ?? [];
    $valeurs = $_POST['valeurs'] ?? [];

    // Validation
    if (empty($recipeName)) {
        throw new Exception("Le nom de la recette est obligatoire.");
    }
    if ($waterVolume <= 0) {
        throw new Exception("Le volume d'eau doit être supérieur à 0.");
    }
    if (empty($nutriments)) {
        throw new Exception("Vous devez sélectionner au moins un nutriment.");
    }

    // Démarrer une transaction
    $pdo->beginTransaction();

    // 1. Insérer la recette
    $duration = 0; // Par défaut, peut être modifié si nécessaire
    $stmt = $pdo->prepare("
        INSERT INTO RECIPE (recipe_name, water_quantity, duration, creation_date)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$recipeName, $waterVolume, $duration]);
    $recipeId = $pdo->lastInsertId();

    // 2. Insérer les nutriments associés
    foreach ($nutriments as $nutrientName) {
        // Récupérer l'ID du nutriment
        $stmtNut = $pdo->prepare("SELECT id_nutrient FROM NUTRIENT WHERE nutrient_name = ?");
        $stmtNut->execute([$nutrientName]);
        $nutrient = $stmtNut->fetch(PDO::FETCH_ASSOC);

        if (!$nutrient) {
            // Si le nutriment n'existe pas, le créer
            $stmtCreate = $pdo->prepare("
                INSERT INTO NUTRIENT (nutrient_name, creation_date)
                VALUES (?, NOW())
            ");
            $stmtCreate->execute([$nutrientName]);
            $nutrientId = $pdo->lastInsertId();
        } else {
            $nutrientId = $nutrient['id_nutrient'];
        }

        // Récupérer le pourcentage pour ce nutriment
        $percentage = (int)($valeurs[$nutrientName] ?? 0);

        // Insérer la relation PERCENTAGE
        $stmtPercent = $pdo->prepare("
            INSERT INTO PERCENTAGE (percentage, id_nutrient, id_recipe, creation_date)
            VALUES (?, ?, ?, NOW())
        ");
        $stmtPercent->execute([$percentage, $nutrientId, $recipeId]);
    }

    // Valider la transaction
    $pdo->commit();

    // Rediriger vers la page recettes avec message de succès
    header("Location: /recettes.php?success=1");
    exit();

} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction si elle a été commencée
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Rediriger avec message d'erreur
    header("Location: /recettes.php?error=" . urlencode($e->getMessage()));
    exit();
}
