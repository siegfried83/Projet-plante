<?php
// Configuration de la base de données MySQL
// Serveur de base de données distant (AlwaysData)
$host = 'mysql-optiplant.alwaysdata.net';
// Nom de la base de données (attention à la différence entre le nom d'utilisateur et le nom de la base)
$dbname = 'optiplant_bd'; // Nom réel de la base de données distante
$username = 'optiplant'; // Nom d'utilisateur de la base de données
$password = 'Optiplant@123'; // Mot de passe de la base de données

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Configuration des options PDO pour une meilleure gestion des erreurs et des résultats
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'erreur de connexion, on affiche un message clair et on arrête le script
    die("<h3>Erreur de connexion à la base de données distante</h3><p>" . $e->getMessage() . "</p>");
}
?>
