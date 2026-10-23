<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Teacher tool registry
    |--------------------------------------------------------------------------
    |
    | Only entries in this registry are exposed through the teacher discovery
    | and execution APIs. The core Blueprint namespace remains domain-neutral.
    |
    */
    'tools' => [
        'assessment-positioning' => [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-positioning',
        ],
        'assessment-score-calculator' => [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-score-calculator',
        ],
        'assessment-results-analyzer' => [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-results-analyzer',
        ],
    ],
];
