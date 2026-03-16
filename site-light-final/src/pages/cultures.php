



<?php
session_start();

// Vérification de la session
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/db.php'; // Connexion MySQL pour les recettes
// Initialisation des données par défaut avec des valeurs vides (pas de données hardcodées)
$bacs = [];
// On initialise 4 bacs par défaut car l'interface en prévoit 4, mais avec des valeurs neutres
for ($i = 1; $i <= 4; $i++) {
    $bacs[$i] = [
        'name' => "Bac $i", 
        'plant' => null,
        'temp' => '--', 
        'soil_humidity' => '--', 
        'light' => '--', 
        'ph' => '--', 
        'cam_status' => 'offline' // Offline par défaut, deviendra online si on détecte une activité récente ou via une autre logique
    ];
}

// Récupération des noms depuis la base de données SQL (PLANTER -> PLANT -> RECIPE)
try {
    $sql = "
        SELECT pl.id_planter, p.variety
        FROM PLANTER pl
        JOIN PLANT p ON pl.id_plant = p.id_plant
    ";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $bacId = (int)$row['id_planter'];
        if (isset($bacs[$bacId])) {
            $bacs[$bacId]['plant'] = htmlspecialchars($row['variety']);
        }
    }
} catch (PDOException $e) {
    // Si la table n'existe pas ou erreur de connexion, on ignore l'erreur pour ne pas bloquer l'affichage
}

$waterLevel = 0;
$globalLight = 0;
$energyProduced = 0;
$bladeInclination = 0;

// Requête InfluxDB
$query = 'from(bucket: "'.INFLUXDB_BUCKET.'") |> range(start: -1h) |> filter(fn: (r) => r["_measurement"] == "optiplant") |> last()';

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
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Pour éviter les problèmes SSL en localhost

$result = curl_exec($ch);
curl_close($ch);

if ($result) {
    // Parser CSV simple sans dépendre de la position des colonnes
    // On split par lignes
    $lines = preg_split('/\r\n|\r|\n/', $result);
    // On trouve les headers pour chaque bloc (InfluxDB renvoie plusieurs tables concaténées)
    $currentHeaders = [];
    
    foreach ($lines as $line) {
        // Ignorer les lignes vides
        if (trim($line) === '') continue;

        $data = str_getcsv($line);
        
        // Si c'est une ligne d'annotation (datatype), on l'ignore souvent mais elle commence souvent par #
        if (isset($data[0]) && strpos($data[0], '#') === 0) continue;

        // Si c'est une ligne de header (contient _field et _value)
        // InfluxDB headers commencent souvent par une colonne vide ou "result"
        if (in_array('_field', $data) && in_array('_value', $data)) {
            $currentHeaders = $data;
            continue;
        }

        // Si on n'a pas encore de headers pour ce bloc, on continue
        if (empty($currentHeaders)) continue;
        
        // On map les données avec les headers courants
        $row = [];
        $isValidRow = false;
        $countHeaders = count($currentHeaders);
        $countData = count($data);
        
        // Sécurité pour éviter offset indefini
        for ($i = 0; $i < $countHeaders; $i++) {
            if (isset($data[$i])) {
                $headerName = $currentHeaders[$i];
                // Si le header est vide (première colonne souvent), on peut l'ignorer ou lui donner un nom
                if ($headerName === '') $headerName = '_index'; 
                $row[$headerName] = $data[$i];
                $isValidRow = true;
            }
        }
        
        if (!$isValidRow) continue;

        // Extraction des valeurs utiles avec les noms de colonnes exacts
        // InfluxDB retourne souvent des colonnes vides ou des annotations, on se fie aux headers
        $field = isset($row['_field']) ? $row['_field'] : '';
        $value = isset($row['_value']) ? $row['_value'] : '';
        
        // Recherche du tag identifiant le bac
        // Dans les logs, le tag s'appelle 'tag' ou 'dispositif'
        $tag_bac = '';
        if (isset($row['tag'])) $tag_bac = $row['tag'];
        elseif (isset($row['bac'])) $tag_bac = $row['bac'];
        elseif (isset($row['device'])) $tag_bac = $row['device'];
        elseif (isset($row['host'])) $tag_bac = $row['host'];

        // Recherche du dispositif (pour la cuve ou la météo locale)
        $dispositif = isset($row['dispositif']) ? $row['dispositif'] : '';

        // Si pas de valeur ou field, on skip
        if ($field === '' || $value === '') continue;

        // Mise à jour Bacs
        $bacId = 0;
        if ($tag_bac) {
             if (preg_match('/bac.*?(\d+)/i', $tag_bac, $matches)) {
                 $bacId = (int)$matches[1];
             } elseif (is_numeric($tag_bac)) {
                 $bacId = (int)$tag_bac;
             }
        }

        if ($bacId > 0 && isset($bacs[$bacId])) {
            // Si on reçoit des données, le bac est en ligne
            $bacs[$bacId]['cam_status'] = 'online';

            if ($field == 'temp_sol') $bacs[$bacId]['temp'] = round((float)$value, 1);
            if ($field == 'humi_sol') $bacs[$bacId]['soil_humidity'] = round((float)$value, 1);
            if ($field == 'luminosite' || $field == 'light') $bacs[$bacId]['light'] = round((float)$value);
            if ($field == 'ph' || $field == 'ph_sol') $bacs[$bacId]['ph'] = round((float)$value, 1);
        }
        
        // Mise à jour Cuve & Système
        if ($dispositif === 'gestion_centrale' || $tag_bac === 'gestion_centrale') {
            if ($field === 'niveau_eau_bac') {
                $waterLevel = round((float)$value);
            }
            if ($field === 'energie_produite') {
                $energyProduced = round((float)$value, 2);
            }
            if ($field === 'inclinaison_pales') {
                $bladeInclination = round((float)$value, 1);
            }
        }
        
        // Luminosité globale (station météo)
        if (($dispositif == 'station_meteo_locale' || $tag_bac == 'station_meteo_locale') && ($field == 'ensoleillement' || $field == 'luminosite')) {
            $globalLight = round((float)$value);
        }
    }
    
    // Appliquer la luminosité globale aux bacs
    if ($globalLight > 0) {
        foreach ($bacs as &$b) {
            $b['light'] = $globalLight;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Cultures - Optiplant</title>
    <!-- Inclusion des icônes FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inclusion du style commun du menu -->
    <link rel="stylesheet" href="/css/style_menu.css">
    <link rel="stylesheet" href="/css/style_cultures.css">
</head>
<body>

<?php include 'menu.php'; ?>

<div class="main-grid">
    <!-- Colonne Gauche : Sidebar (Cuve + Énergie) -->
    <div class="sidebar-col">
        
        <!-- Panneau Énergie & Pales -->
        <div class="energy-panel">
            <h3 style="text-align: center; margin: 0 0 15px 0; color: #2c3e50;">Système</h3>
            
            <div class="energy-item">
                <div class="energy-icon"><i class="fas fa-bolt"></i></div>
                <div class="energy-info">
                    <span class="energy-val-big"><?php echo $energyProduced; ?> W</span>
                    <span class="energy-label">Production</span>
                </div>
            </div>
            
            <div class="energy-item">
                <div class="energy-icon" style="background: #e67e22;"><i class="fa-solid fa-solar-panel"></i></div>
                <div class="energy-info">
                    <span class="energy-val-big"><?php echo $bladeInclination; ?>°</span>
                    <span class="energy-label">Inclinaison</span>
                </div>
            </div>
        </div>

        <!-- Panneau Cuve -->
        <div class="tank-panel <?php echo $waterLevel < 25 ? 'tank-low-warning' : ''; ?>">
            <h3 style="margin: 0; color: #2980b9;">Réserve d'Eau</h3>
            <div class="tank-visual">
                <div class="water" style="height: <?php echo $waterLevel; ?>%;"><div class="water-wave"></div></div>
                <div class="tank-threshold"></div>
            </div>
            <div class="tank-value" style="font-size: 1.5em; <?php echo $waterLevel < 25 ? 'color: #8b0000;' : ''; ?>"><?php echo $waterLevel; ?>%</div>
            <div style="color: <?php echo $waterLevel < 25 ? '#8b0000' : '#7f8c8d'; ?>; font-size: 0.8em;">
                <?php echo $waterLevel * 10; ?>L / 1000L
            </div>
        </div>

    </div>

    <!-- Colonne Droite : Bacs -->
    <div class="bacs-grid">
        <?php foreach ($bacs as $id => $bac): ?>
            <a href="bac.php?id=<?php echo $id; ?>" class="bac-card-link">
            <div class="bac-card <?php echo $bac['cam_status']; ?> <?php echo (is_numeric($bac['soil_humidity']) && (float)$bac['soil_humidity'] < 40) ? 'tank-low-warning' : ''; ?>">
                <div class="bac-header">
                    <span class="bac-title"><?php echo htmlspecialchars($bac['name']); ?></span>
                    <span class="plant-badge <?php echo $bac['plant'] ? '' : 'plant-badge-empty'; ?>">
                        <i class="fas fa-seedling" style="font-size:0.85em"></i>
                        <?php echo $bac['plant'] ? $bac['plant'] : 'Vide'; ?>
                    </span>
                    <span class="status-dot <?php echo $bac['cam_status'] == 'online' ? 'status-online' : 'status-offline'; ?>" title="Camera <?php echo $bac['cam_status']; ?>"></span>
                </div>
                
                <div class="cam-feed">
                    <?php if ($bac['cam_status'] == 'online'): ?>
                        <!-- Image caméra -->
                        <?php 
                        $imagePath = "/assets/images/bac$id.jpg";
                        
                        // Vérifier l'existence du fichier
                        $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.jpg';
                        if (!file_exists($fullPath)) {
                            $imagePath = "/assets/images/bac$id.png";
                            $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.png';
                        }
                        if (!file_exists($fullPath)) {
                            $imagePath = "/assets/images/bac$id.jpeg";
                            $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.jpeg';
                        }
                        
                        // Afficher l'image si elle existe
                        if(file_exists($fullPath)) {
                            echo '<img class="cam-image" data-base-src="'.$imagePath.'" src="'.$imagePath.'?v='.time().'" style="width:100%; height:100%; object-fit: cover;">';
                        } else {
                            echo '<img src="https://placehold.co/400x280/2c3e50/ffffff?text=Camera+'.$id.'+Live" style="width:100%; height:100%; object-fit: cover;">';
                        }
                        ?>
                    <?php else: ?>
                        <div style="width:100%; height:100%; position:relative; overflow:hidden;">
                            <?php 
                            $imagePath = "/assets/images/bac$id.jpg";
                            $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.jpg';
                            
                            if (!file_exists($fullPath)) {
                                $imagePath = "/assets/images/bac$id.png";
                                $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.png';
                            }
                            if (!file_exists($fullPath)) {
                                $imagePath = "/assets/images/bac$id.jpeg";
                                $fullPath = __DIR__ . '/../../public/assets/images/bac' . $id . '.jpeg';
                            }
                            
                            if(file_exists($fullPath)) {
                                echo '<img class="cam-image" data-base-src="'.$imagePath.'" src="'.$imagePath.'?v='.time().'" style="width:100%; height:100%; object-fit: cover; filter: grayscale(100%) brightness(50%);">';
                            }
                            ?>
                            <div class="cam-placeholder" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%;">
                                ⚠️ Signal perdu<br>
                                <small>Vérifier la connexion</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sensors-data">
                    <div class="sensor-item">
                        <span class="sensor-label">Température</span>
                        <span class="sensor-val"><?php echo $bac['temp']; ?>°C</span>
                    </div>
                    <div class="sensor-item">
                        <span class="sensor-label">Humidité Sol</span>
                        <span class="sensor-val" <?php echo (is_numeric($bac['soil_humidity']) && (float)$bac['soil_humidity'] < 40) ? 'style="color: #8b0000;"' : ''; ?>><?php echo $bac['soil_humidity']; ?>%</span>
                    </div>
                    <div class="sensor-item">
                        <span class="sensor-label">Luminosité</span>
                        <span class="sensor-val"><?php echo $bac['light']; ?> lux</span>
                    </div>
                    <div class="sensor-item">
                        <span class="sensor-label">pH Sol</span>
                        <span class="sensor-val"><?php echo $bac['ph']; ?></span>
                    </div>
                </div>
            </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Rafraîchissement des images caméra sans freeze : préchargement avant swap
function refreshCamImages() {
    const images = document.querySelectorAll('.cam-image');
    const timestamp = new Date().getTime();
    images.forEach(function(img) {
        const baseSrc = img.getAttribute('data-base-src');
        if (!baseSrc) return;
        const preloader = new Image();
        preloader.onload = function() {
            img.src = preloader.src;
        };
        preloader.src = baseSrc + '?v=' + timestamp;
    });
}
setInterval(refreshCamImages, 3000);
</script>
</body>
</html>
