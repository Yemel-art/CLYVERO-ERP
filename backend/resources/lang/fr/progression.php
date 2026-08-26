<?php

return [
    'errors' => [
        'missing_policy' => 'Configurez la politique de passage de cette année scolaire avant de générer les décisions.',
        'incomplete_grades' => 'Les notes finales sont incomplètes (:count matière(s) manquante(s)).',
        'cross_school_year' => 'Les années scolaires de deux établissements différents ne peuvent pas être combinées.',
        'missing_target_class' => 'Aucune classe de destination n’est configurée pour :class.',
        'override_disabled' => 'Les dérogations du conseil de classe sont désactivées par cette politique.',
        'year_not_upcoming' => 'Seule une année scolaire à venir peut être activée.',
        'year_not_after_current' => 'La nouvelle année scolaire doit commencer après l’année scolaire active.',
        'invalid_transition_state' => 'Le passage exige une année source active et une année cible à venir.',
    ],
    'messages' => [
        'policy_retrieved' => 'Politique de passage récupérée.',
        'policy_saved' => 'Politique de passage enregistrée.',
        'decisions_generated' => 'Décisions académiques générées.',
        'decisions_retrieved' => 'Décisions académiques récupérées.',
        'decision_updated' => 'Décision académique mise à jour.',
        'rollover_completed' => 'Passage à la nouvelle année scolaire terminé.',
    ],
];
