<?php

return [
    'users' => [
        'columns' => ['id', 'name', 'email']
    ],
    'posts' => [
        'columns' => ['id', 'title', 'content', 'user_id', 'category_id'],
        'relations' => [
            'user' => [
                'foreign_key' => 'user_id',
                'local_key' => 'id',
                'related_table' => 'users'
            ],
            'category' => [
                'foreign_key' => 'category_id',
                'local_key' => 'id',
                'related_table' => 'categories'
            ],
            'comment' => [
                'foreign_key' => 'id',
                'local_key' => 'post_id',
                'related_table' => 'comments'
            ]
        ]
    ],
    'comments' => [
        'columns' => ['id', 'content', 'post_id', 'user_id'],
        'relations' => [
            'post' => [
                'foreign_key' => 'post_id',
                'local_key' => 'id',
                'related_table' => 'posts'
            ],
            'user' => [
                'foreign_key' => 'user_id',
                'local_key' => 'id',
                'related_table' => 'users'
            ]
        ]
    ],
    'categories' => [
        'columns' => ['id', 'name']
    ]
];
