<?php

return [
    'users' => [
        'relations' => [
            'posts' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'posts',
                'relations' => [
                    'user' => [
                        'local_key' => 'user_id',
                        'foreign_key' => 'id',
                        'related_table' => 'users',
                        'relations' => [
                            'likes' => [
                                'local_key' => 'id',
                                'foreign_key' => 'user_id',
                                'related_table' => 'likes',
                                'relations' => []
                            ]
                        ]
                    ],
                ],
                
            ],
            'comments' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'comments',

            ],
            'likes' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'likes',
            ]
        ]
    ],
];
