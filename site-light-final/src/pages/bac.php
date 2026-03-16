<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../config/config.php';

// Validation de l'id du bac
$bacId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bacId < 1 || $bacId > 99) {
    header("Location: /cultures.php");
    exit();
}

$success = '';
$error   = '';

// ─── Paramètres de temps pour les graphiques ─────────────────────────────────
$timeRanges = [
    '1h' => '1 heure',
    '6h' => '6 heures',
    '12h' => '12 heures',
    '24h' => '24 heures',
    '48h' => '48 heures',
    '7d' => '7 jours',
    '30d' => '30 jours'
];

$aggregations = [
    '1m' => '1 minute',
    '5m' => '5 minutes',
    '10m' => '10 minutes',
    '30m' => '30 minutes',
    '1h' => '1 heure',
    '6h' => '6 heures',
    '1d' => '1 jour'
];

// Récupérer les paramètres depuis l'URL (avec valeurs par défaut)
$selectedRange = isset($_GET['range']) && array_key_exists($_GET['range'], $timeRanges) ? $_GET['range'] : '24h';
$selectedAggregation = isset($_GET['agg']) && array_key_exists($_GET['agg'], $aggregations) ? $_GET['agg'] : '30m';

// ─── Récupération des données historiques InfluxDB ───────────────────────────
$historyData = [
    'timestamps' => [],
    'temp' => [],
    'humidity' => [],
    'ph' => []
];

// Données actuelles du bac
$currentData = [
    'temp' => '--',
    'soil_humidity' => '--',
    'light' => '--',
    'ph' => '--',
    'cam_status' => 'offline'
];

// Requête pour les données actuelles (dernière valeur)
$queryLast = 'from(bucket: "'.INFLUXDB_BUCKET.'") 
    |> range(start: -1h) 
    |> filter(fn: (r) => r["_measurement"] == "optiplant") 
    |> last()';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, INFLUXDB_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $queryLast);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Token " . INFLUXDB_TOKEN,
    "Content-Type: application/vnd.flux",
    "Accept: application/csv"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$resultLast = curl_exec($ch);
curl_close($ch);

if ($resultLast) {
    $lines = preg_split('/\r\n|\r|\n/', $resultLast);
    $currentHeaders = [];
    
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $data = str_getcsv($line);
        if (isset($data[0]) && strpos($data[0], '#') === 0) continue;
        
        if (in_array('_field', $data) && in_array('_value', $data)) {
            $currentHeaders = $data;
            continue;
        }
        
        if (empty($currentHeaders)) continue;
        
        $row = [];
        for ($i = 0; $i < count($currentHeaders); $i++) {
            if (isset($data[$i])) {
                $headerName = $currentHeaders[$i] ?: '_index';
                $row[$headerName] = $data[$i];
            }
        }
        
        $field = $row['_field'] ?? '';
        $value = $row['_value'] ?? '';
        
        // Récupérer le tag du bac (comme dans cultures.php)
        $tag_bac = '';
        if (isset($row['tag'])) $tag_bac = $row['tag'];
        elseif (isset($row['bac'])) $tag_bac = $row['bac'];
        elseif (isset($row['device'])) $tag_bac = $row['device'];
        elseif (isset($row['host'])) $tag_bac = $row['host'];
        
        if ($field === '' || $value === '') continue;
        
        // Vérifier si c'est le bon bac
        $matchBac = 0;
        if ($tag_bac) {
            if (preg_match('/bac.*?(\d+)/i', $tag_bac, $matches)) {
                $matchBac = (int)$matches[1];
            } elseif (is_numeric($tag_bac)) {
                $matchBac = (int)$tag_bac;
            }
        }
        
        if ($matchBac === $bacId) {
            $currentData['cam_status'] = 'online';
            if ($field === 'temp_sol') $currentData['temp'] = round((float)$value, 1);
            if ($field === 'humi_sol') $currentData['soil_humidity'] = round((float)$value, 1);
            if ($field === 'luminosite' || $field === 'light') $currentData['light'] = round((float)$value);
            if ($field === 'ph' || $field === 'ph_sol') $currentData['ph'] = round((float)$value, 1);
        }
    }
}

// Requête pour l'historique - Récupère toutes les données et filtre en PHP
$query = 'from(bucket: "'.INFLUXDB_BUCKET.'") 
    |> range(start: -'.$selectedRange.') 
    |> filter(fn: (r) => r["_measurement"] == "optiplant") 
    |> filter(fn: (r) => r["_field"] == "temp_sol" or r["_field"] == "humi_sol" or r["_field"] == "ph" or r["_field"] == "ph_sol")
    |> aggregateWindow(every: '.$selectedAggregation.', fn: mean, createEmpty: false)
    |> yield(name: "mean")';

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

$result = curl_exec($ch);
curl_close($ch);

if ($result) {
    $lines = preg_split('/\r\n|\r|\n/', $result);
    $currentHeaders = [];
    $tempData = [];
    
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $data = str_getcsv($line);
        if (isset($data[0]) && strpos($data[0], '#') === 0) continue;
        
        if (in_array('_field', $data) && in_array('_value', $data)) {
            $currentHeaders = $data;
            continue;
        }
        
        if (empty($currentHeaders)) continue;
        
        $row = [];
        for ($i = 0; $i < count($currentHeaders); $i++) {
            if (isset($data[$i])) {
                $headerName = $currentHeaders[$i] ?: '_index';
                $row[$headerName] = $data[$i];
            }
        }
        
        $field = $row['_field'] ?? '';
        $value = $row['_value'] ?? '';
        $time = $row['_time'] ?? '';
        
        // Récupérer le tag du bac (comme dans cultures.php)
        $tag_bac = '';
        if (isset($row['tag'])) $tag_bac = $row['tag'];
        elseif (isset($row['bac'])) $tag_bac = $row['bac'];
        elseif (isset($row['device'])) $tag_bac = $row['device'];
        elseif (isset($row['host'])) $tag_bac = $row['host'];
        
        if ($field === '' || $value === '' || $time === '') continue;
        
        // Filtrer par bac ID (comme dans cultures.php)
        $matchBac = 0;
        if ($tag_bac) {
            if (preg_match('/bac.*?(\d+)/i', $tag_bac, $matches)) {
                $matchBac = (int)$matches[1];
            } elseif (is_numeric($tag_bac)) {
                $matchBac = (int)$tag_bac;
            }
        }
        
        // Ne traiter que les données du bac concerné
        if ($matchBac !== $bacId) continue;
        
        $timestamp = strtotime($time);
        // Utiliser jour/heure pour éviter les conflits entre jours
        $timeLabel = date('d H:i', $timestamp);
        
        if (!isset($tempData[$timeLabel])) {
            $tempData[$timeLabel] = ['temp' => null, 'humidity' => null, 'ph' => null, 'timestamp' => $timestamp];
        }
        
        if ($field === 'temp_sol') $tempData[$timeLabel]['temp'] = round((float)$value, 1);
        if ($field === 'humi_sol') $tempData[$timeLabel]['humidity'] = round((float)$value, 1);
        if ($field === 'ph' || $field === 'ph_sol') $tempData[$timeLabel]['ph'] = round((float)$value, 1);
    }
    
    // Trier par timestamp
    uasort($tempData, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    // Format de date adapté à la période sélectionnée
    $dateFormat = 'H:i'; // Par défaut: juste l'heure
    if (in_array($selectedRange, ['7d', '30d'])) {
        $dateFormat = 'd/m H:i'; // Pour les longues périodes: date + heure
    } elseif (in_array($selectedRange, ['48h'])) {
        $dateFormat = 'd H:i'; // Pour 48h: jour + heure
    }
    
    foreach ($tempData as $key => $values) {
        $historyData['timestamps'][] = date($dateFormat, $values['timestamp']);
        $historyData['temp'][] = $values['temp'];
        $historyData['humidity'][] = $values['humidity'];
        $historyData['ph'][] = $values['ph'];
    }
}

// ─── Traitement du formulaire ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Dissocier la plante du bac ---
    if ($action === 'detach') {
        try {
            $stmt = $pdo->prepare("UPDATE PLANTER SET id_plant = NULL WHERE id_planter = ?");
            $stmt->execute([$bacId]);
            $success = "Plante dissociée du bac avec succès.";
        } catch (PDOException $e) {
            $error = "Erreur lors de la dissociation : " . htmlspecialchars($e->getMessage());
        }

    // --- Associer une plante existante ---
    } elseif ($action === 'associate') {
        $plantId = (int)($_POST['existing_plant_id'] ?? 0);
        if ($plantId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE PLANTER SET id_plant = ? WHERE id_planter = ?");
                $stmt->execute([$plantId, $bacId]);
                $success = "Plante associée au bac avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur lors de l'association : " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = "Veuillez sélectionner une plante.";
        }

    // --- Créer une nouvelle plante et l'associer ---
    } elseif ($action === 'create_plant') {
        $variety           = trim($_POST['variety'] ?? '');
        $germinationRecipe = (int)($_POST['germination_recipe'] ?? 0) ?: null;
        $pousseRecipe      = (int)($_POST['vegetative_recipe']  ?? 0) ?: null;
        $floraisonRecipe   = (int)($_POST['flowering_recipe']   ?? 0) ?: null;
        $groupId           = (int)($_POST['id_group']           ?? 0) ?: null;

        if ($variety === '') {
            $error = "La variété est obligatoire.";
        } else {
            try {
                $pdo->beginTransaction();

                // Créer la plante
                $stmt = $pdo->prepare("
                    INSERT INTO PLANT (variety, germination_recipe, vegetative_recipe, flowering_recipe, id_group, creation_date)
                    VALUES (?, ?, ?, ?, ?, CURDATE())
                ");
                $stmt->execute([$variety, $germinationRecipe, $pousseRecipe, $floraisonRecipe, $groupId]);
                $newPlantId = $pdo->lastInsertId();

                // Lier la plante au bac
                $stmt2 = $pdo->prepare("UPDATE PLANTER SET id_plant = ? WHERE id_planter = ?");
                $stmt2->execute([$newPlantId, $bacId]);

                $pdo->commit();
                $success = "Plante \"" . htmlspecialchars($variety) . "\" créée et associée au bac avec succès.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la création : " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ─── Chargement des données ──────────────────────────────────────────────────

// Info bac courant + plante associée
$bac   = null;
$plant = null;
try {
    $stmt = $pdo->prepare("
        SELECT pl.id_planter, pl.planter_name, pl.id_plant,
               p.variety,
               p.germination_recipe, p.vegetative_recipe, p.flowering_recipe,
               p.id_group,
               r1.recipe_name AS germ_name,
               r2.recipe_name AS pousse_name,
               r3.recipe_name AS flor_name,
               g.group_name
        FROM PLANTER pl
        LEFT JOIN PLANT p       ON pl.id_plant = p.id_plant
        LEFT JOIN RECIPE r1     ON p.germination_recipe  = r1.id_recipe
        LEFT JOIN RECIPE r2     ON p.vegetative_recipe   = r2.id_recipe
        LEFT JOIN RECIPE r3     ON p.flowering_recipe    = r3.id_recipe
        LEFT JOIN GROUP_TABLE g ON p.id_group            = g.id_group
        WHERE pl.id_planter = ?
    ");
    $stmt->execute([$bacId]);
    $row = $stmt->fetch();
    if ($row) {
        $bac = $row;
        if ($row['id_plant']) {
            $plant = $row;
        }
    }
} catch (PDOException $e) {
    $error = "Erreur chargement bac : " . htmlspecialchars($e->getMessage());
}

// Liste toutes les recettes
$recipes = [];
try {
    $stmtR = $pdo->query("SELECT id_recipe, recipe_name FROM RECIPE ORDER BY recipe_name ASC");
    $recipes = $stmtR->fetchAll();
} catch (PDOException $e) {}

// Liste tous les groupes
$groups = [];
try {
    $stmtG = $pdo->query("SELECT id_group, group_name FROM GROUP_TABLE ORDER BY group_name ASC");
    $groups = $stmtG->fetchAll();
} catch (PDOException $e) {}

// Liste toutes les plantes existantes (pour association rapide)
$allPlants = [];
try {
    $stmtP = $pdo->query("SELECT id_plant, variety FROM PLANT ORDER BY variety ASC");
    $allPlants = $stmtP->fetchAll();
} catch (PDOException $e) {}

$bacName = $bac ? htmlspecialchars($bac['planter_name']) : "Bac $bacId";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $bacName; ?> - Optiplant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style_menu.css">
     <link rel="stylesheet" href="/css/style_bac.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
      
    </style>
</head>
<body>
<?php include __DIR__ . '/menu.php'; ?>

<div class="page-wrapper">

    <a href="cultures.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour aux cultures</a>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ── Carte : Plante actuelle ─────────────────────────────────────── -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-seedling"></i>
            <?php echo $bacName; ?>
            <?php if ($plant): ?>
                <span class="badge"><?php echo htmlspecialchars($plant['variety']); ?></span>
            <?php else: ?>
                <span class="badge badge-none">Aucune plante</span>
            <?php endif; ?>
        </div>

        <?php if ($plant): ?>
            <div class="plant-info-grid">
                <div class="plant-info-item">
                    <div class="plant-info-label">Variété</div>
                    <div class="plant-info-val"><?php echo htmlspecialchars($plant['variety']); ?></div>
                </div>
                <div class="plant-info-item">
                    <div class="plant-info-label">Groupe</div>
                    <div class="plant-info-val"><?php echo $plant['group_name'] ? htmlspecialchars($plant['group_name']) : '—'; ?></div>
                </div>
                <div class="plant-info-item">
                    <div class="plant-info-label"><i class="fas fa-flask" style="font-size:0.9em"></i> Germination</div>
                    <div class="plant-info-val"><?php echo $plant['germ_name']   ? htmlspecialchars($plant['germ_name'])   : '—'; ?></div>
                </div>
                <div class="plant-info-item">
                    <div class="plant-info-label"><i class="fas fa-leaf" style="font-size:0.9em"></i> Végétative</div>
                    <div class="plant-info-val"><?php echo $plant['pousse_name'] ? htmlspecialchars($plant['pousse_name']) : '—'; ?></div>
                </div>
                <div class="plant-info-item">
                    <div class="plant-info-label"><i class="fas fa-spa" style="font-size:0.9em"></i> Floraison</div>
                    <div class="plant-info-val"><?php echo $plant['flor_name']   ? htmlspecialchars($plant['flor_name'])   : '—'; ?></div>
                </div>
            </div>
            <hr class="divider">
            <form method="POST" onsubmit="return confirm('Dissocier la plante de ce bac ?');">
                <input type="hidden" name="action" value="detach">
                <button type="submit" class="btn btn-red"><i class="fas fa-unlink"></i> Dissocier la plante</button>
            </form>
        <?php else: ?>
            <div class="empty-plant">
                <i class="fas fa-seedling"></i>
                Aucune plante n'est associée à ce bac.
            </div>
        <?php endif; ?>
    </div>


    <!-- ── Carte : Graphiques des capteurs ─────────────────────────────── -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-line"></i> Historique des capteurs
            <span class="current-range-info">
                <i class="fas fa-clock"></i>
                <?php echo $timeRanges[$selectedRange]; ?> / <?php echo $aggregations[$selectedAggregation]; ?>
            </span>
        </div>
        
        <!-- Contrôles de temps -->
        <form method="GET" class="time-controls" id="timeControlForm">
            <input type="hidden" name="id" value="<?php echo $bacId; ?>">
            
            <div class="time-control-group">
                <label class="time-control-label"><i class="fas fa-calendar-alt"></i> Période</label>
                <select name="range" class="time-control-select" onchange="document.getElementById('timeControlForm').submit()">
                    <?php foreach ($timeRanges as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $selectedRange === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="time-control-group">
                <label class="time-control-label"><i class="fas fa-layer-group"></i> Agrégation</label>
                <select name="agg" class="time-control-select" onchange="document.getElementById('timeControlForm').submit()">
                    <?php foreach ($aggregations as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $selectedAggregation === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-refresh">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
        </form>
        
        <?php if (empty($historyData['timestamps'])): ?>
            <div class="no-data-message">
                <i class="fas fa-chart-area"></i>
                Aucune donnée disponible pour ce bac sur cette période.<br>
                <small>Essayez d'augmenter la période ou vérifiez que le bac envoie des données.</small>
            </div>
        <?php else: ?>
            <div class="charts-section">
                <!-- Graphique Température -->
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-thermometer-half temp-icon"></i>
                        Température du sol (°C)
                    </div>
                    <canvas id="tempChart"></canvas>
                </div>
                
                <!-- Graphique Humidité -->
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-tint humidity-icon"></i>
                        Humidité du sol (%)
                    </div>
                    <canvas id="humidityChart"></canvas>
                </div>
                
                <!-- Graphique pH -->
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-flask ph-icon"></i>
                        pH du sol
                    </div>
                    <canvas id="phChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Bouton pour afficher le formulaire d'ajout ─────────────────── -->
    <div class="toggle-section">
        <button class="btn-toggle" id="togglePlantForm" onclick="togglePlantSection()">
            <i class="fas fa-plus"></i>
            <span id="toggleText">Ajouter / Gérer une plante</span>
        </button>
    </div>

    <!-- ── Carte : Associer / Créer une plante (section cachée) ───────── -->
    <div class="hidden-section" id="plantFormSection">
    <div class="card">
        <div class="card-title"><i class="fas fa-plus-circle"></i> Gérer la plante</div>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('tab-existing', this)">
                <i class="fas fa-list"></i> Plante existante
            </button>
            <button class="tab-btn" onclick="showTab('tab-new', this)">
                <i class="fas fa-plus"></i> Nouvelle plante
            </button>
        </div>

        <!-- Associer une plante existante -->
        <div id="tab-existing" class="tab-panel active">
            <?php if (empty($allPlants)): ?>
                <p style="color:#7f8c8d; font-size:0.9em;">Aucune plante dans la base de données. Créez-en une ci-dessous.</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="associate">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="existing_plant_id">Sélectionner une plante</label>
                            <select name="existing_plant_id" id="existing_plant_id" required>
                                <option value="">— Choisir une plante —</option>
                                <?php foreach ($allPlants as $p): ?>
                                    <option value="<?php echo $p['id_plant']; ?>"
                                        <?php echo ($plant && $plant['id_plant'] == $p['id_plant']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['variety']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-blue"><i class="fas fa-link"></i> Associer au bac</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Créer une nouvelle plante -->
        <div id="tab-new" class="tab-panel">
            <form method="POST">
                <input type="hidden" name="action" value="create_plant">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="variety">Variété <span style="color:#e74c3c">*</span></label>
                        <input type="text" name="variety" id="variety" placeholder="Ex : Tomate cerise, Basilic..." required>
                    </div>

                    <div class="form-group">
                        <label for="id_group">Groupe</label>
                        <select name="id_group" id="id_group">
                            <option value="">— Aucun groupe —</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?php echo $g['id_group']; ?>"><?php echo htmlspecialchars($g['group_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="germination_recipe"><i class="fas fa-flask"></i> Recette germination</label>
                        <select name="germination_recipe" id="germination_recipe">
                            <option value="">— Aucune —</option>
                            <?php foreach ($recipes as $r): ?>
                                <option value="<?php echo $r['id_recipe']; ?>"><?php echo htmlspecialchars($r['recipe_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pousse_recipe"><i class="fas fa-leaf"></i> Recette végétative</label>
                        <select name="vegetative_recipe" id="vegetative_recipe">
                            <option value="">— Aucune —</option>
                            <?php foreach ($recipes as $r): ?>
                                <option value="<?php echo $r['id_recipe']; ?>"><?php echo htmlspecialchars($r['recipe_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="floraison_recipe"><i class="fas fa-spa"></i> Recette floraison</label>
                        <select name="flowering_recipe" id="flowering_recipe">
                            <option value="">— Aucune —</option>
                            <?php foreach ($recipes as $r): ?>
                                <option value="<?php echo $r['id_recipe']; ?>"><?php echo htmlspecialchars($r['recipe_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <br>
                <button type="submit" class="btn btn-green"><i class="fas fa-plus"></i> Créer et associer au bac</button>
            </form>
        </div>
    </div>
    </div> <!-- Fermeture hidden-section -->

</div>

<script>
// Données historiques pour les graphiques - à passer au JS
const historyData = <?php echo json_encode($historyData); ?>;
</script>

<!-- JavaScript séparé pour bac.php -->
<script src="/js/script_bac.js"></script>
</body>
</html>
