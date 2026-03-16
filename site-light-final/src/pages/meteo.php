<?php
session_start();

// Vérification de la session - redirection vers login si non connecté
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

// Récupération des données météo via Open-Meteo API
$weatherData = null;
try {
    $lat = 43.1245; // Latitude (Toulon)
    $lon = 6.0108;  // Longitude
    $apiUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,wind_direction_10m&daily=temperature_2m_max,temperature_2m_min,weather_code,sunrise,sunset&timezone=Europe/Paris&forecast_days=5";
    
    $json = @file_get_contents($apiUrl);
    if ($json) {
        $weatherData = json_decode($json, true);
        $GLOBALS['weatherData'] = $weatherData; // Pour menu.php
    }
} catch (Exception $e) {
    $weatherData = null;
}

// Fonction pour convertir le code météo en description et icône
function getWeatherInfo($code) {
    $weather = [
        0 => ['desc' => 'Ciel dégagé', 'icon' => 'fa-sun', 'color' => '#f39c12'],
        1 => ['desc' => 'Principalement dégagé', 'icon' => 'fa-sun', 'color' => '#f39c12'],
        2 => ['desc' => 'Partiellement nuageux', 'icon' => 'fa-cloud-sun', 'color' => '#3498db'],
        3 => ['desc' => 'Couvert', 'icon' => 'fa-cloud', 'color' => '#7f8c8d'],
        45 => ['desc' => 'Brouillard', 'icon' => 'fa-smog', 'color' => '#95a5a6'],
        48 => ['desc' => 'Brouillard givrant', 'icon' => 'fa-smog', 'color' => '#95a5a6'],
        51 => ['desc' => 'Bruine légère', 'icon' => 'fa-cloud-rain', 'color' => '#3498db'],
        53 => ['desc' => 'Bruine modérée', 'icon' => 'fa-cloud-rain', 'color' => '#3498db'],
        55 => ['desc' => 'Bruine dense', 'icon' => 'fa-cloud-rain', 'color' => '#3498db'],
        61 => ['desc' => 'Pluie légère', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2980b9'],
        63 => ['desc' => 'Pluie modérée', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2980b9'],
        65 => ['desc' => 'Pluie forte', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2980b9'],
        71 => ['desc' => 'Neige légère', 'icon' => 'fa-snowflake', 'color' => '#ecf0f1'],
        73 => ['desc' => 'Neige modérée', 'icon' => 'fa-snowflake', 'color' => '#ecf0f1'],
        75 => ['desc' => 'Neige forte', 'icon' => 'fa-snowflake', 'color' => '#ecf0f1'],
        80 => ['desc' => 'Averses légères', 'icon' => 'fa-cloud-sun-rain', 'color' => '#3498db'],
        81 => ['desc' => 'Averses modérées', 'icon' => 'fa-cloud-sun-rain', 'color' => '#3498db'],
        82 => ['desc' => 'Averses violentes', 'icon' => 'fa-cloud-showers-heavy', 'color' => '#2980b9'],
        95 => ['desc' => 'Orage', 'icon' => 'fa-bolt', 'color' => '#9b59b6'],
        96 => ['desc' => 'Orage avec grêle', 'icon' => 'fa-bolt', 'color' => '#9b59b6'],
        99 => ['desc' => 'Orage violent', 'icon' => 'fa-bolt', 'color' => '#8e44ad'],
    ];
    return $weather[$code] ?? ['desc' => 'Inconnu', 'icon' => 'fa-question', 'color' => '#7f8c8d'];
}

// Jours de la semaine en français
function getDayName($date) {
    $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $d = new DateTime($date);
    return $days[(int)$d->format('w')];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Météo - Optiplant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style_menu.css">
    <link rel="stylesheet" href="/css/style_meteo.css">
</head>
<body>

<?php include __DIR__ . '/menu.php'; ?>

<div class="dashboard-container">
    <!-- Widget Météo -->
    <div class="weather-section">
        <?php if ($weatherData && isset($weatherData['current'])): ?>
            <?php 
            $current = $weatherData['current'];
            $daily = $weatherData['daily'];
            $weatherInfo = getWeatherInfo($current['weather_code']);
            ?>
            <div class="weather-description">
                <strong><?php echo $weatherInfo['desc']; ?></strong> - Toulon
            </div>
            <div class="weather-current">
                <div class="weather-main">
                    <i class="fas <?php echo $weatherInfo['icon']; ?> weather-icon" style="color: <?php echo $weatherInfo['color']; ?>"></i>
                    <div class="weather-temp">
                        <?php echo round($current['temperature_2m']); ?><sup>°C</sup>
                    </div>
                </div>
                <div class="weather-details">
                    <div class="weather-detail">
                        <i class="fas fa-temperature-half"></i>
                        <div class="value"><?php echo round($current['apparent_temperature']); ?>°C</div>
                        <div class="label">Ressenti</div>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-droplet"></i>
                        <div class="value"><?php echo $current['relative_humidity_2m']; ?>%</div>
                        <div class="label">Humidité</div>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-wind"></i>
                        <div class="value"><?php echo round($current['wind_speed_10m']); ?> km/h</div>
                        <div class="label">Vent</div>
                    </div>
                </div>
            </div>
            
            <!-- Prévisions -->
            <div class="weather-forecast">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <?php $dayInfo = getWeatherInfo($daily['weather_code'][$i]); ?>
                    <div class="forecast-day">
                        <div class="day-name"><?php echo getDayName($daily['time'][$i]); ?></div>
                        <i class="fas <?php echo $dayInfo['icon']; ?>" style="color: <?php echo $dayInfo['color']; ?>"></i>
                        <div class="temps">
                            <span class="temp-max"><?php echo round($daily['temperature_2m_max'][$i]); ?>°</span>
                            <span class="temp-min"><?php echo round($daily['temperature_2m_min'][$i]); ?>°</span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <div class="weather-error">
                <i class="fas fa-cloud-question"></i>
                <p>Impossible de charger les données météo</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
