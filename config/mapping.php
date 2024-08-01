<?php

return [
    'users' => [
        'columns' => ['id', 'name', 'email'],
        'relations' => [
            'posts' => [
                'foreign_key' => 'id',
                'local_key' => 'user_id',
                'related_table' => 'posts'
            ],
            'comments' => [
                'foreign_key' => 'id',
                'local_key' => 'user_id',
                'related_table' => 'comments'
            ],
            'likes' => [
                'foreign_key' => 'id',
                'local_key' => 'user_id',
                'related_table' => 'likes'
            ]
        ]
    ],
    'posts' => [
        'columns' => ['id', 'title', 'content', 'user_id', 'category_id'],
        'relations' => [
            'user' => [
                'foreign_key' => 'user_id',
                'local_key' => 'id',
                'related_table' => 'users',
                'relations' => [
                    'comments' => [
                        'foreign_key' => 'post_id',
                        'local_key' => 'id',
                        'related_table' => 'comments'
                    ]
                ]
            ],
            'category' => [
                'foreign_key' => 'category_id',
                'local_key' => 'id',
                'related_table' => 'categories',
                'relations' => [
                    'posts' => [
                        'foreign_key' => 'category_id',
                        'local_key' => 'id',
                        'related_table' => 'posts'
                    ]
                ]
            ],
            'comment' => [
                'foreign_key' => 'id',
                'local_key' => 'post_id',
                'related_table' => 'comments',
                'relations' => [
                    'user' => [
                        'foreign_key' => 'user_id',
                        'local_key' => 'id',
                        'related_table' => 'users'
                    ]
                ]
            ]
        ]
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
    ]
];
