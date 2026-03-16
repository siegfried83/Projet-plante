<?php
/**
 * FICHIER DE DEBUG OBSOLÈTE
 * Ce fichier testait les connexions MySQL locales (localhost:3306-3308 avec root/[vide])
 * Il n'est plus utilisé en production car la base de données est sur:
 * - Host: mysql-optiplant.alwaysdata.net
 * - Port: 3306
 * - User: optiplant
 * - Database: optiplant_bd
 * 
 * Ce fichier peut être supprimé.
 * Pour tester la connexion, vérifiez db.php ou utilisez des tests en ligne.
 */

// Code original commenté:
/*
$hosts = ['127.0.0.1', 'localhost'];
$ports = [3306, 3307, 3308, 8889];
$user = 'root';
$pass = '';

echo "Test de connexion MySQL...\n";

foreach ($hosts as $h) {
    foreach ($ports as $p) {
        try {
            echo "Tentative sur $h:$p... ";
            $dsn = "mysql:host=$h;port=$p;dbname=mysql;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass);
            echo "SUCCÈS !\n";
            echo "Le serveur tourne sur $h:$p\n";
            exit(0);
        } catch (PDOException $e) {
            echo "Echec (" . $e->getMessage() . ")\n";
        }
    }
}
echo "Aucun serveur MySQL trouvé avec l'utilisateur 'root' et mot de passe vide.\n";
*/
?>