<?php
/*
 * SGA - Systeme de Gestion des Auditoires
 * PHP procedural uniquement, persistance JSON/TXT.
 */

function charger_json($chemin_fichier, $champs_obligatoires) {
    if (!file_exists($chemin_fichier)) {
        throw new Exception("Fichier introuvable : " . $chemin_fichier);
    }
    $contenu = file_get_contents($chemin_fichier);
    if ($contenu === false || trim($contenu) === '') {
        throw new Exception("Fichier vide ou illisible : " . $chemin_fichier);
    }
    $donnees = json_decode($contenu, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($donnees)) {
        throw new Exception("JSON malforme dans " . $chemin_fichier . " : " . json_last_error_msg());
    }
    foreach ($donnees as $i => $ligne) {
        if (!is_array($ligne)) {
            throw new Exception("Ligne malformee a l'indice $i dans $chemin_fichier");
        }
        foreach ($champs_obligatoires as $champ) {
            if (!array_key_exists($champ, $ligne) || $ligne[$champ] === '') {
                throw new Exception("Valeur manquante '$champ' a l'indice $i dans $chemin_fichier");
            }
        }
    }
    return $donnees;
}

function charger_salles($chemin_fichier) {
    $salles = charger_json($chemin_fichier, ['id', 'designation', 'capacite']);
    foreach ($salles as &$s) {
        $s['capacite'] = intval($s['capacite']);
        if ($s['capacite'] <= 0) throw new Exception("Capacite invalide pour la salle " . $s['id']);
    }
    return $salles;
}

function charger_promotions($chemin_fichier) {
    $promotions = charger_json($chemin_fichier, ['id', 'libelle', 'effectif']);
    foreach ($promotions as &$p) {
        $p['effectif'] = intval($p['effectif']);
        if ($p['effectif'] <= 0) throw new Exception("Effectif invalide pour la promotion " . $p['id']);
    }
    return $promotions;
}

function charger_cours($chemin_fichier) {
    $cours = charger_json($chemin_fichier, ['id', 'intitule', 'volume', 'type', 'groupe']);
    foreach ($cours as &$c) {
        $c['volume'] = intval($c['volume']);
        if ($c['volume'] <= 0) throw new Exception("Volume horaire invalide pour le cours " . $c['id']);
        if (!in_array($c['type'], ['tronc', 'option'])) throw new Exception("Type de cours invalide pour " . $c['id']);
    }
    return $cours;
}

function charger_options($chemin_fichier) {
    $options = charger_json($chemin_fichier, ['id', 'libelle', 'promotion', 'effectif']);
    foreach ($options as &$o) {
        $o['effectif'] = intval($o['effectif']);
        if ($o['effectif'] <= 0) throw new Exception("Effectif invalide pour l'option " . $o['id']);
    }
    return $options;
}

function chercher_par_id($tableau, $id) {
    foreach ($tableau as $element) {
        if ($element['id'] === $id) return $element;
    }
    return null;
}

function effectif_groupe($id_groupe, $promotions, $options) {
    $promotion = chercher_par_id($promotions, $id_groupe);
    if ($promotion !== null) return intval($promotion['effectif']);
    $option = chercher_par_id($options, $id_groupe);
    if ($option !== null) return intval($option['effectif']);
    throw new Exception("Groupe inconnu : " . $id_groupe);
}

function salle_disponible($planning, $id_salle, $creneau) {
    foreach ($planning as $ligne) {
        if ($ligne['id_salle'] === $id_salle && $ligne['creneau'] === $creneau) {
            return false;
        }
    }
    return true;
}

function capacite_suffisante($salles, $id_salle, $effectif) {
    foreach ($salles as $salle) {
        if ($salle['id'] === $id_salle) {
            return intval($effectif) <= intval($salle['capacite']);
        }
    }
    return false;
}

function creneau_libre_groupe($planning, $id_groupe, $creneau) {
    foreach ($planning as $ligne) {
        if ($ligne['id_groupe'] === $id_groupe && $ligne['creneau'] === $creneau) {
            return false;
        }
    }
    return true;
}

function construire_creneaux() {
    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
    $plages = [
        ['08:00', '12:00'],
        ['13:00', '17:00']
    ];
    $creneaux = [];
    foreach ($jours as $jour) {
        foreach ($plages as $plage) {
            $creneaux[] = [
                'id' => $jour . '_' . $plage[0] . '_' . $plage[1],
                'jour' => $jour,
                'debut' => $plage[0],
                'fin' => $plage[1]
            ];
        }
    }
    return $creneaux;
}

function generer_planning($salles, $promotions, $cours, $options, $creneaux_disponibles) {
    $planning = [];
    $non_planifies = [];
    usort($cours, function($a, $b) use ($promotions, $options) {
        return effectif_groupe($b['groupe'], $promotions, $options) - effectif_groupe($a['groupe'], $promotions, $options);
    });
    usort($salles, function($a, $b) { return intval($a['capacite']) - intval($b['capacite']); });

    foreach ($cours as $c) {
        $id_groupe = $c['groupe'];
        $effectif = effectif_groupe($id_groupe, $promotions, $options);
        $place = false;
        foreach ($creneaux_disponibles as $creneau) {
            if (!creneau_libre_groupe($planning, $id_groupe, $creneau['id'])) continue;
            foreach ($salles as $salle) {
                if (capacite_suffisante($salles, $salle['id'], $effectif) && salle_disponible($planning, $salle['id'], $creneau['id'])) {
                    $planning[] = [
                        'jour' => $creneau['jour'],
                        'debut' => $creneau['debut'],
                        'fin' => $creneau['fin'],
                        'creneau' => $creneau['id'],
                        'id_salle' => $salle['id'],
                        'code_cours' => $c['id'],
                        'intitule' => $c['intitule'],
                        'id_groupe' => $id_groupe,
                        'effectif' => $effectif
                    ];
                    $place = true;
                    break 2;
                }
            }
        }
        if (!$place) {
            $non_planifies[] = $c['id'] . ' (' . $id_groupe . ', effectif ' . $effectif . ')';
        }
    }
    if (!empty($non_planifies)) {
        throw new Exception('Cours non planifies : ' . implode(', ', $non_planifies));
    }
    return $planning;
}

function sauvegarder_planning($planning, $chemin_fichier) {
    $lignes = [];
    foreach ($planning as $p) {
        $lignes[] = implode(';', [$p['jour'], $p['debut'], $p['fin'], $p['id_salle'], $p['code_cours'], $p['id_groupe']]);
    }
    if (file_put_contents($chemin_fichier, implode(PHP_EOL, $lignes)) === false) {
        throw new Exception("Impossible de sauvegarder le planning TXT");
    }
}

function sauvegarder_planning_json($planning, $chemin_fichier) {
    $json = json_encode($planning, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($chemin_fichier, $json) === false) {
        throw new Exception("Impossible de sauvegarder le planning JSON");
    }
}

function charger_planning($chemin_fichier) {
    if (!file_exists($chemin_fichier)) throw new Exception("Planning introuvable : " . $chemin_fichier);
    if (substr($chemin_fichier, -5) === '.json') {
        return charger_json($chemin_fichier, ['jour','debut','fin','id_salle','code_cours','id_groupe']);
    }
    $planning = [];
    $lignes = file($chemin_fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lignes as $i => $ligne) {
        $champs = explode(';', $ligne);
        if (count($champs) !== 6) throw new Exception("Ligne planning malformee numero " . ($i + 1));
        list($jour, $debut, $fin, $id_salle, $code_cours, $id_groupe) = $champs;
        $planning[] = compact('jour', 'debut', 'fin', 'id_salle', 'code_cours', 'id_groupe') + ['creneau'=>$jour.'_'.$debut.'_'.$fin];
    }
    return $planning;
}

function afficher_planning_html($planning) {
    $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
    $plages = [['08:00','12:00'], ['13:00','17:00']];
    echo '<table class="planning"><thead><tr><th>Creneau</th>';
    foreach ($jours as $jour) echo '<th>' . htmlspecialchars($jour) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($plages as $plage) {
        echo '<tr><th>' . $plage[0] . ' - ' . $plage[1] . '</th>';
        foreach ($jours as $jour) {
            $cellule = '';
            foreach ($planning as $p) {
                if ($p['jour'] === $jour && $p['debut'] === $plage[0] && $p['fin'] === $plage[1]) {
                    $cellule .= '<div><strong>' . htmlspecialchars($p['id_salle']) . '</strong><br>' . htmlspecialchars($p['code_cours']) . '<br>' . htmlspecialchars($p['id_groupe']) . '</div>';
                }
            }
            echo '<td>' . ($cellule ?: '-') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function detecter_conflits($planning) {
    $conflits = [];
    for ($i = 0; $i < count($planning); $i++) {
        for ($j = $i + 1; $j < count($planning); $j++) {
            $meme_creneau = $planning[$i]['jour'] === $planning[$j]['jour'] && $planning[$i]['debut'] === $planning[$j]['debut'] && $planning[$i]['fin'] === $planning[$j]['fin'];
            if ($meme_creneau && $planning[$i]['id_salle'] === $planning[$j]['id_salle']) {
                $conflits[] = 'Conflit salle ' . $planning[$i]['id_salle'] . ' le ' . $planning[$i]['jour'] . ' ' . $planning[$i]['debut'];
            }
            if ($meme_creneau && $planning[$i]['id_groupe'] === $planning[$j]['id_groupe']) {
                $conflits[] = 'Conflit groupe ' . $planning[$i]['id_groupe'] . ' le ' . $planning[$i]['jour'] . ' ' . $planning[$i]['debut'];
            }
        }
    }
    return $conflits;
}

function rapport_occupation($planning, $salles, $creneaux_disponibles, $chemin_fichier) {
    $total_creneaux = count($creneaux_disponibles);
    $lignes = ["Salle;Occupes;Libres;Taux"];
    foreach ($salles as $salle) {
        $occupes = 0;
        foreach ($planning as $p) if ($p['id_salle'] === $salle['id']) $occupes++;
        $libres = $total_creneaux - $occupes;
        $taux = round(($occupes / $total_creneaux) * 100, 2);
        $lignes[] = $salle['id'] . ';' . $occupes . ';' . $libres . ';' . $taux . '%';
    }
    file_put_contents($chemin_fichier, implode(PHP_EOL, $lignes));
}

function modifier_affectation($planning, $code_cours, $nouvelle_salle, $nouveau_creneau, $salles, $promotions, $options) {
    foreach ($planning as $index => $p) {
        if ($p['code_cours'] === $code_cours) {
            $effectif = isset($p['effectif']) ? intval($p['effectif']) : effectif_groupe($p['id_groupe'], $promotions, $options);
            if (!capacite_suffisante($salles, $nouvelle_salle, $effectif)) return false;
            $tmp = $planning;
            unset($tmp[$index]);
            if (!salle_disponible($tmp, $nouvelle_salle, $nouveau_creneau['id'])) return false;
            if (!creneau_libre_groupe($tmp, $p['id_groupe'], $nouveau_creneau['id'])) return false;
            $planning[$index]['id_salle'] = $nouvelle_salle;
            $planning[$index]['jour'] = $nouveau_creneau['jour'];
            $planning[$index]['debut'] = $nouveau_creneau['debut'];
            $planning[$index]['fin'] = $nouveau_creneau['fin'];
            $planning[$index]['creneau'] = $nouveau_creneau['id'];
            return $planning;
        }
    }
    return false;
}
?>
