<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation de la Base de Données Optiplant</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #333; }
    </style>
</head>
<body>
    <h1>Installation de la Base de Données Optiplant</h1>

<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';

echo "<p class='info'>Tentative de connexion au serveur MySQL local ($host, utilisateur: $username)...</p>";

try {
    // Connexion sans base de données pour pouvoir la créer
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>Connexion réussie.</p>";
    
    // Lecture du fichier SQL
    $sqlFile = __DIR__ . '/database_setup.sql';
    if (!file_exists($sqlFile)) {
        die("<p class='error'>Erreur : Le fichier database_setup.sql est introuvable.</p>");
    }
    
    $sql = file_get_contents($sqlFile);
    
    echo "<p class='info'>Exécution du script SQL de création...</p>";
    
    // Exécution des requêtes
    // Note: PDO supporte l'exécution de multiples requêtes séparées par des points-virgules
    $pdo->exec($sql);
    
    echo "<p class='success'>Base de données 'optiplant' et tables créées avec succès !</p>";
    echo "<p>Vous pouvez maintenant utiliser l'application.</p>";
    echo "<p><a href='index.php'>Retour à l'accueil</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Erreur PDO : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

</body>
</html>
