<?php
session_start();

// Vérification de la session - redirection vers login si non connecté
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Optiplant - Accueil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style_menu.css">
    <link rel="stylesheet" href="css/style_index.css">
</head>
<body>

<?php include '../src/pages/menu.php'; ?>

<div class="dashboard-container">
    <div class="welcome-section">
        <h1><i class="fas fa-seedling"></i>Bienvenue sur Optiplant</h1>
        <p>Système de gestion intelligente pour l'agriculture hydroponique</p>
    </div>


    
  
</div>

</body>
</html>
