<?php
session_start();

// Si déjà connecté, rediriger vers l'accueil
if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // 'login' ou 'register'

// Configuration base de données
$db_host = 'mysql-optiplant.alwaysdata.net';
$db_name = 'optiplant_bd';
$db_user = 'optiplant';
$db_pass = 'Optiplant@123';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    try {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8",
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        if ($action === 'register') {
            // INSCRIPTION
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            
            if (empty($username) || empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs.';
            } elseif ($password !== $password_confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } else {
                // Vérifier si l'utilisateur existe déjà
                $stmt = $pdo->prepare('SELECT id_user FROM USER_TABLE WHERE username = :username OR email_address = :email LIMIT 1');
                $stmt->execute([':username' => $username, ':email' => $email]);
                
                if ($stmt->fetch()) {
                    $error = 'Ce nom d\'utilisateur ou email est déjà utilisé.';
                } else {
                    // Créer l'utilisateur avec mot de passe hashé
                    $hashedPassword = password_hash($password, PASSWORD_ARGON2ID,
                    [
                        'memory_cost' => 1024 * 128,
                        'time_cost' => 3,
                        'threads' => 4
                    ]
                    );
                    $stmt = $pdo->prepare('INSERT INTO USER_TABLE (username, email_address, password, role) VALUES (:username, :email, :password, :role)');
                    $stmt->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $hashedPassword,
                        ':role' => 'user'
                    ]);
                    $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
                    $mode = 'login';
                }
            }
        } else {
            // CONNEXION
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Veuillez remplir tous les champs.';
            } else {
                // Rechercher l'utilisateur par username ou email
                $stmt = $pdo->prepare('SELECT id_user, username, email_address, password, role FROM USER_TABLE WHERE username = :login OR email_address = :login LIMIT 1');
                $stmt->execute([':login' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log = $pdo->prepare("INSERT INTO LOGIN_LOG (user_id, ip_address, success) VALUES (:uid,:ip,1)");
                    $log->execute([
                    ':uid' => $user['id_user'],
                    ':ip' => $ip
                     ]);
                    // Connexion réussie - on garde $_SESSION['user'] pour compatibilité
                    $_SESSION['user'] = $user['email_address'];
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: /index.php');
                    exit;
                } else {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log = $pdo->prepare("INSERT INTO LOGIN_LOG (user_id, ip_address, success) VALUES (NULL,:ip,0)");
                    $log->execute([':ip'=>$ip]);
                    $error = 'Identifiants incorrects.';
                }
            }
        }
        
        $pdo = null;
    } catch (PDOException $e) {
        $error = 'Erreur de connexion à la base de données.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Optiplant - <?php echo $mode === 'register' ? 'Inscription' : 'Connexion'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style_login.css">
</head>
<body>

<div class="login-container">
    <h1 class="brand-title"><i class="fas fa-seedling"></i> OPTIPLANT</h1>
    
    <div class="tabs">
        <a href="?mode=login" class="tab <?php echo $mode === 'login' ? 'active' : ''; ?>">
            <i class="fas fa-sign-in-alt"></i> Connexion
        </a>
        <a href="?mode=register" class="tab <?php echo $mode === 'register' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i> Inscription
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($mode === 'register'): ?>
    <!-- FORMULAIRE INSCRIPTION -->
    <form class="login-form" method="POST" action="?mode=register">
        <input type="hidden" name="action" value="register">
        
        <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="username" placeholder="Nom d'utilisateur" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" placeholder="Adresse email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password" placeholder="Mot de passe (min. 6 caractères)" required>
        </div>

        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required>
        </div>

        <button type="submit" class="connect-button">
            <i class="fas fa-user-plus"></i> Créer mon compte
        </button>
    </form>
    
    <?php else: ?>
    <!-- FORMULAIRE CONNEXION -->
    <form class="login-form" method="POST" action="?mode=login">
        <input type="hidden" name="action" value="login">
        
        <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="username" placeholder="Nom d'utilisateur ou email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password" placeholder="Mot de passe" required>
        </div>

        <button type="submit" class="connect-button">
            <i class="fas fa-sign-in-alt"></i> Connexion
        </button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
