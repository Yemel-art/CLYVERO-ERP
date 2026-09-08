<?php

return [
    'errors' => [
        'missing_policy' => 'Configure a promotion policy for this academic year before generating decisions.',
        'incomplete_grades' => 'The final grades are incomplete (:count subject(s) are missing).',
        'cross_school_year' => 'Academic years from different schools cannot be combined.',
        'missing_target_class' => 'No next-year target class is configured for :class.',
        'override_disabled' => 'Class council overrides are disabled by this promotion policy.',
        'year_not_upcoming' => 'Only an upcoming academic year can be activated.',
        'year_not_after_current' => 'The new academic year must start after the active academic year.',
        'invalid_transition_state' => 'Rollover requires one active source year and one upcoming target year.',
    ],
    'messages' => [
        'policy_retrieved' => 'Promotion policy retrieved.',
        'policy_saved' => 'Promotion policy saved.',
        'decisions_generated' => 'Academic decisions generated.',
        'decisions_retrieved' => 'Academic decisions retrieved.',
        'decision_updated' => 'Academic decision updated.',
        'rollover_completed' => 'School-year rollover completed.',
    ],
];
