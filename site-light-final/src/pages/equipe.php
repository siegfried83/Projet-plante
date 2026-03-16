<?php
session_start();

// Vérification de la session
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$team = [
    'Chef de projet' => [
        ['name' => 'Yanis Bouzidi', 'role' => 'Project Manager', 'color' => '#2c3e50']
    ],
    'Supervision Web' => [
        ['name' => 'Victor Brice-Rey', 'role' => 'Développeur FullStack', 'color' => '#3498db'],
        ['name' => 'Melvin Lacote', 'role' => 'Développeur Frontend', 'color' => '#3498db'],
        ['name' => 'Jean Pacteau', 'role' => 'Développeur Backend', 'color' => '#3498db'],
        ['name' => 'Théo Bordes', 'role' => 'Développeur Backend/Frontend', 'color' => '#3498db']

    ],
    'Base de données' => [
        ['name' => 'Akram Maarad', 'role' => 'Développeur BD', 'color' => '#9b59b6'],
        ['name' => 'Antonin Moreau', 'role' => 'Architecte BD', 'color' => '#9b59b6'],
        ['name' => 'Sofiane Beji', 'role' => 'Data Analyst', 'color' => '#9b59b6']
    ],
    'Traitement par IA et vision' => [
        ['name' => 'Théo Bordes', 'role' => 'Ingénieur IA/Vision', 'color' => '#e67e22']
    ],
    'Codesys' => [
        ['name' => 'Minh Ly', 'role' => 'Ingénieur Automatisation', 'color' => '#27ae60']
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L'Équipe - Optiplant</title>
    <!-- Inclusion des icônes FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inclusion du style commun du menu -->
    <link rel="stylesheet" href="/css/style_menu.css">
    <link rel="stylesheet" href="/css/style_equipe.css">
</head>

<body>

    <?php include __DIR__ . '/menu.php'; ?>

    <div class="org-container">
        <h1 style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.2); margin-bottom: 40px;">Organigramme du Projet
        </h1>

        <?php foreach ($team as $section => $members): ?>
            <div class="org-section">
                <div class="org-title"><?php echo $section; ?></div>
                <div class="members-grid">
                    <?php foreach ($members as $member): ?>
                        <?php
                        $initials = "";
                        $parts = explode(" ", $member['name']);
                        foreach ($parts as $part)
                            $initials .= $part[0];
                        ?>
                        <div class="member-card <?php echo $section == 'Chef de projet' ? 'chef' : ''; ?>">
                            <div class="member-avatar" style="background-color: <?php echo $member['color']; ?>;">
                                <?php echo $initials; ?>
                            </div>
                            <div class="member-name"><?php echo $member['name']; ?></div>
                            <!-- <div class="member-role"><?php echo $member['role']; ?></div> -->
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

</body>

</html>