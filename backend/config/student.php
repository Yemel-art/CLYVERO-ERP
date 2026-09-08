<?php

declare(strict_types=1);

return [
    'cycles' => [
        'secondary_general' => 'Secondary General (Secondaire General)',
        'secondary_technical' => 'Secondary Technical (Secondaire Technique)',
    ],

    'specialities' => [
        'MA' => ['code' => 'MA', 'name' => 'Mécanique Automobile', 'name_en' => 'Motor Mechanics', 'sector' => 'industrial', 'cycle' => 'both'],
        'EE' => ['code' => 'EE', 'name' => 'Génie Électricité', 'name_en' => 'Electrical Engineering', 'sector' => 'industrial', 'cycle' => 'both'],
        'BT' => ['code' => 'BT', 'name' => 'Couture sur Mesure', 'name_en' => 'Bespoke Tailoring', 'sector' => 'industrial', 'cycle' => 'both'],
        'CARP' => ['code' => 'CARP', 'name' => 'Menuiserie', 'name_en' => 'Carpentry', 'sector' => 'industrial', 'cycle' => 'both'],
        'WELD' => ['code' => 'WELD', 'name' => 'Soudure', 'name_en' => 'Welding', 'sector' => 'industrial', 'cycle' => 'both'],
        'BC' => ['code' => 'BC', 'name' => 'Maçonnerie', 'name_en' => 'Building and Construction', 'sector' => 'industrial', 'cycle' => 'both'],
        'NUR' => ['code' => 'NUR', 'name' => 'Soins Infirmiers', 'name_en' => 'Nursing', 'sector' => 'tertiary', 'cycle' => 'both'],
        'ACC' => ['code' => 'ACC', 'name' => 'Comptabilité', 'name_en' => 'Accounting', 'sector' => 'tertiary', 'cycle' => 'both'],
        'ESF' => ['code' => 'ESF', 'name' => 'Économie Sociale et Familiale', 'name_en' => 'Home Economics', 'sector' => 'tertiary', 'cycle' => 'both'],
    ],

    'general_streams' => [
        'A' => ['code' => 'A', 'name' => 'Série A — Littéraire', 'name_en' => 'Arts and Literature', 'language' => 'fr'],
        'C' => ['code' => 'C', 'name' => 'Série C — Mathématiques et Sciences Physiques', 'name_en' => 'Mathematics and Physical Sciences', 'language' => 'fr'],
        'D' => ['code' => 'D', 'name' => 'Série D — Sciences de la Vie et de la Terre', 'name_en' => 'Life and Earth Sciences', 'language' => 'fr'],
        'TI' => ['code' => 'TI', 'name' => 'Technologies de l’Information', 'name_en' => 'Information Technology', 'language' => 'fr'],
        'ARTS' => ['code' => 'ARTS', 'name' => 'Arts et Lettres', 'name_en' => 'Arts', 'language' => 'en'],
        'SCI' => ['code' => 'SCI', 'name' => 'Sciences', 'name_en' => 'Science', 'language' => 'en'],
    ],
];
