<?php

return [
    'users' => [
        'columns' => ['id', 'name', 'email'],
        'relations' => [
            'posts' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'posts'
            ],
            'comments' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'comments'
            ],
            'likes' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'likes'
            ]
        ]
    ],
    'posts' => [
        'columns' => ['id', 'title', 'content', 'user_id', 'category_id'],
        'relations' => [
            'user' => [
                'local_key' => 'user_id',
                'foreign_key' => 'id',
                'related_table' => 'users'
            ],
        ],
        
    ],
    'comments' => [
        'columns' => ['id', 'content', 'post_id', 'user_id'],
        'relations' => [
            'likes' => [
                'foreign_key' => 'id',
                'local_key' => 'comment_id',
                'related_table' => 'likes'
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
    ],
    'likes' => [
        'columns' => ['id', 'post_id', 'user_id'],

    ]
];
