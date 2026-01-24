<?php

return [
    'scope' => 'global',
    'popup_prefix' => 'classroom.popup.',
    'allowed_keys' => [
        'classroom.page',
        'auth.login',
        'auth.qlink',
    ],
    'allowed_template_variables' => [
        'user',
        'classroom',
        'locale',
        'page',
    ],
    'default_popups' => [
        [
            'key' => 'invite',
            'title' => 'Invite Parents',
        ],
        [
            'key' => 'homework',
            'title' => 'Homework',
        ],
        [
            'key' => 'links',
            'title' => 'Useful Links',
        ],
        [
            'key' => 'whatsapp',
            'title' => 'Group WhatsApp',
        ],
        [
            'key' => 'important-links',
            'title' => 'Important Links',
        ],
        [
            'key' => 'holidays',
            'title' => 'Holidays',
        ],
        [
            'key' => 'children',
            'title' => 'Children',
        ],
        [
            'key' => 'contacts',
            'title' => 'Important Contacts',
        ],
        [
            'key' => 'food',
            'title' => 'What We Eat',
        ],
        [
            'key' => 'schedule',
            'title' => 'Weekly Schedule',
        ],
    ],
    'max_include_depth' => 5,
];
