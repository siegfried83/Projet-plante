<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

/* Vérifier si l'utilisateur est connecté */
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

/* Connexion base de données */
try {
    $bdd = new PDO(
        "mysql:host=mysql-optiplant.alwaysdata.net;dbname=optiplant_bd;charset=utf8",
        "optiplant",
        "Optiplant@123",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Erreur connexion base de données");
}

/* Récupérer info utilisateur */
$stmt = $bdd->prepare("SELECT username, role FROM USER_TABLE WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$username = $user['username'];
$role = $user['role'];

/* Gestion changement mot de passe */
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ancien_mdp = $_POST["ancien_mdp"] ?? "";
    $nouveau_mdp = $_POST["nouveau_mdp"] ?? "";
    $confirmer_mdp = $_POST["confirmer_mdp"] ?? "";

    if (!$ancien_mdp || !$nouveau_mdp || !$confirmer_mdp) {
        $error = "Tous les champs sont obligatoires.";
    } elseif ($nouveau_mdp !== $confirmer_mdp) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($nouveau_mdp) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match('/\d/', $nouveau_mdp)) {
        $error = "Le mot de passe doit contenir au moins un chiffre.";
    } elseif (preg_match('/\s/', $nouveau_mdp)) {
        $error = "Le mot de passe ne doit pas contenir d'espaces.";
    } else {
        $stmt = $bdd->prepare("SELECT password FROM USER_TABLE WHERE id_user = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_pass = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_pass) {
            $error = "Utilisateur introuvable.";
        } elseif (!password_verify($ancien_mdp, $user_pass['password'])) {
            $error = "Ancien mot de passe incorrect.";
        } else {
            $hash = password_hash($nouveau_mdp, PASSWORD_ARGON2ID, [
                "memory_cost" => 1024 * 128,
                "time_cost" => 3,
                "threads" => 4
            ]);
            $stmt = $bdd->prepare("UPDATE USER_TABLE SET password = ? WHERE id_user = ?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            $success = "Mot de passe mis à jour avec succès.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte utilisateur - Optiplant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/style_menu.css">
    <link rel="stylesheet" href="/css/style_compte.css">


</head>

<body>

    <?php include __DIR__ . '/menu.php'; ?>

    <div class="compte-container">

        <div class="compte-header">
            <h2><?php echo htmlspecialchars($username); ?></h2>
            <p>Compte : <?php echo htmlspecialchars($role); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="password" name="ancien_mdp" placeholder="Ancien mot de passe" required>
            </div>
            <div class="form-group">
                <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe" required>
            </div>
            <div class="form-group">
                <input type="password" name="confirmer_mdp" placeholder="Confirmer le mot de passe" required>
            </div>
            <button class="button"><i class="fas fa-key"></i> Modifier le mot de passe</button>
        </form>

        <a class="back-link" href="index.php"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>

    </div>

</body>

</html>