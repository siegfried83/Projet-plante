<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Assurer l'inclusion de config.php pour les constantes InfluxDB
require_once __DIR__ . '/../config/config.php';

// Fonction pour récupérer la dernière mise à jour InfluxDB
function getLastUpdate() {
    // Si on a déjà l'info en session et qu'elle a moins de 30s, on l'utilise
    if (isset($_SESSION['last_update_ts']) && (time() - $_SESSION['last_update_check'] < 30)) {
        return $_SESSION['last_update_ts'];
    }

    $query = 'from(bucket: "'.INFLUXDB_BUCKET.'") |> range(start: -2h) |> filter(fn: (r) => r["_measurement"] == "optiplant") |> last() |> keep(columns: ["_time"]) |> limit(n:1)';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, INFLUXDB_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . INFLUXDB_TOKEN,
        "Content-Type: application/vnd.flux",
        "Accept: application/csv"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Timeout court pour ne pas bloquer le chargement du menu
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $result) {
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            // Ignorer headers et annotations
            if (empty(trim($line)) || strpos($line, '#') === 0 || strpos($line, ',result,') !== false) continue;
            
            $data = str_getcsv($line);
            // La colonne _time est généralement vers la fin, on cherche un format date
            foreach ($data as $col) {
                // Format ISO 8601 partiel (2024-...)
                if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $col)) {
                    $dt = new DateTime($col);
                    $dt->setTimezone(new DateTimeZone('Europe/Paris'));
                    $formatted = $dt->format('d/m H:i');
                    
                    // Mise en cache session
                    $_SESSION['last_update_ts'] = $formatted;
                    $_SESSION['last_update_check'] = time();
                    return $formatted;
                }
            }
        }
    }
    return '--:--';
}

$lastUpdate = getLastUpdate();

function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : '';
}

$username = isset($_SESSION['user_id']) ? $_SESSION['username'] ?? 'Utilisateur' : 'Invité';
?>

<div class="header-nav">
    <div class="logo-section" style="font-weight: bold; color: #2c3e50; font-size: 1.2em; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-leaf" style="color: #27ae60;"></i>
        <span>Optiplant</span>
        <div style="font-weight: normal; font-size: 0.8em; color: #7f8c8d; display: flex; flex-direction: column; line-height: 1.2; margin-left: 10px;">
            <span><?php echo htmlspecialchars($username); ?></span>
            <span style="font-size: 0.85em; color: #95a5a6;" title="Dernière mise à jour des données">
                <i class="fas fa-clock" style="font-size: 0.8em;"></i> <?php echo $lastUpdate; ?>
            </span>
        </div>
    </div>
    <div class="nav-links">
        <a href="/index.php" class="<?php echo isActive('index.php'); ?>"><i class="fas fa-seedling"></i> Accueil</a>
        <a href="/cultures.php" class="<?php echo isActive('cultures.php'); ?>"><i class="fas fa-seedling"></i> Cultures</a>
        <a href="/meteo.php" class="<?php echo isActive('meteo.php'); ?>"><i class="fas fa-cloud-sun"></i> Météo</a>
        <a href="/recettes.php" class="<?php echo isActive('recettes.php'); ?>"><i class="fas fa-book-open"></i> Recettes</a>
        <a href="/equipe.php" class="<?php echo isActive('equipe.php'); ?>"><i class="fas fa-users"></i> Équipe</a>
        <!-- <a href="/compte.php" class="<?php echo isActive('compte.php'); ?>"><i class="fas fa-user-circle"></i> Compte</a> -->
        <a href="/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</div>

<!-- JavaScript séparé pour menu-->
<script src="/js/script_menu.js"></script>