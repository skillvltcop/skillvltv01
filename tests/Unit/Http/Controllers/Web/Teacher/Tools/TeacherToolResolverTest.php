<?php

use App\Http\Controllers\Web\Teacher\Tools\TeacherToolResolver;

it('resolves supported teacher tools to their views', function () {
    $resolver = new TeacherToolResolver();

    expect($resolver->viewFor('assessment-positioning'))
        ->toBe('teacher.tools.assessment-positioning');
});

it('returns null for unknown teacher tools', function () {
    $resolver = new TeacherToolResolver();

    expect($resolver->viewFor('unknown-tool'))
        ->toBeNull();
});
