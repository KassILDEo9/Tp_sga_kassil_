<?php
/* Exemple simple de formulaire pour saisir une salle et l'enregistrer dans salles.json */
require_once __DIR__ . '/includes/fonctions.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $salles = charger_salles(__DIR__ . '/data/salles.json');
        $salles[] = [
            'id' => trim($_POST['id']),
            'designation' => trim($_POST['designation']),
            'capacite' => intval($_POST['capacite'])
        ];
        file_put_contents(__DIR__ . '/data/salles.json', json_encode($salles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = 'Salle enregistree avec succes.';
    } catch (Exception $e) { $message = 'Erreur : ' . $e->getMessage(); }
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Saisie salle</title></head><body>
<h1>Ajouter une salle</h1><p><?= htmlspecialchars($message) ?></p>
<form method="post">
<label>Identifiant <input name="id" required></label><br>
<label>Designation <input name="designation" required></label><br>
<label>Capacite <input type="number" name="capacite" min="1" required></label><br>
<button type="submit">Enregistrer</button>
</form></body></html>
