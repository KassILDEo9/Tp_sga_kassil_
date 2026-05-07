<?php
require_once __DIR__ . '/includes/fonctions.php';
$messages = [];
$planning = [];
try {
    $salles = charger_salles(__DIR__ . '/data/salles.json');
    $promotions = charger_promotions(__DIR__ . '/data/promotions.json');
    $cours = charger_cours(__DIR__ . '/data/cours.json');
    $options = charger_options(__DIR__ . '/data/options.json');
    $creneaux = construire_creneaux();
    $messages[] = 'Donnees chargees avec succes.';
    $planning = generer_planning($salles, $promotions, $cours, $options, $creneaux);
    $messages[] = 'Planning genere sans conflit.';
    sauvegarder_planning($planning, __DIR__ . '/data/planning.txt');
    sauvegarder_planning_json($planning, __DIR__ . '/data/planning.json');
    $messages[] = 'Planning sauvegarde dans data/planning.txt et data/planning.json.';
    $conflits = detecter_conflits($planning);
    if (empty($conflits)) $messages[] = 'Aucun conflit detecte.';
    rapport_occupation($planning, $salles, $creneaux, __DIR__ . '/data/rapport_occupation.txt');
    $messages[] = 'Rapport d\'occupation genere dans data/rapport_occupation.txt.';
} catch (Exception $e) {
    $messages[] = 'Erreur : ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGA - Gestion des Auditoires</title>
    <style>
        body{font-family:Arial,sans-serif;margin:30px;background:#f6f8fb;color:#1f2937}.container{max-width:1100px;margin:auto;background:white;padding:25px;border-radius:10px;box-shadow:0 2px 12px #ddd}h1{color:#0f3b63}.ok{background:#e7f8ee;border-left:5px solid #16a34a;padding:10px;margin:8px 0}.planning{width:100%;border-collapse:collapse;margin-top:20px}.planning th{background:#0f3b63;color:white}.planning th,.planning td{border:1px solid #ccc;padding:10px;vertical-align:top}.planning td div{background:#eef2ff;margin:4px;padding:6px;border-radius:5px}
    </style>
</head>
<body><div class="container">
<h1>Systeme de Gestion des Auditoires et des Horaires</h1>
<?php foreach ($messages as $m): ?><div class="ok"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
<?php if (!empty($planning)) afficher_planning_html($planning); ?>
</div></body></html>
