<?php

return [
    'users' => [
        'relations' => [
            'posts' => [
                'foreign_key' => 'id',
                'local_key' => 'user_id',
                'related_table' => 'posts'
            ],
            'comments' => [
                'foreign_key' => 'id',
                'local_key' => 'user_id',
                'related_table' => 'comments',
                'relations' => [
                    'user' => [
                        'foreign_key' => 'user_id',
                        'local_key' => 'id',
                        'related_table' => 'users'
                    ],
                ],
            ],
        ]
    ],
    'posts' => [
        'relations' => [
            'user' => [
                'foreign_key' => 'user_id',
                'local_key' => 'id',
                'related_table' => 'users'
            ],
        ]
    ],
];
