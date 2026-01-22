<?php

return [
    'scope' => 'global',
    'popup_prefix' => 'classroom.popup.',
    'allowed_keys' => [
        'classroom.page',
        'auth.login',
    ],
    'allowed_template_variables' => [
        'user',
        'classroom',
        'locale',
        'page',
    ],
    'max_include_depth' => 5,
];
