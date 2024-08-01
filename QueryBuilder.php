<?php

class QueryBuilder
{
    private $mappingTable;
    private $selectColumns = [];
    private $relSelectColumns = [];
    private $joinQueries = [];
    private $relationData = [];
    private $mainTable;
    private $pdo; // PDO nesnesi

    public function __construct(string $mainTable, PDO $pdo)
    {

        $this->mappingTable = require 'config/mapping.php';
        $this->mainTable = $mainTable;
        devoLog($this->mainTable, "ana tablo adı", $this->mainTable . '.sql');
        $this->pdo = $pdo; // PDO nesnesini al
        $this->buildQuery();
    }

    private function buildQuery()
    {
        // Ana tablonun sütunlarını seç
        foreach ($this->mappingTable[$this->mainTable]['columns'] as $column) {
            $this->selectColumns[] = "{$this->mainTable}.{$column} AS {$this->mainTable}_{$column}";
        }

        devoLog($this->selectColumns, "hint", $this->mainTable . '.sql');
        // İlişkileri ekle
        $this->addRelations($this->mainTable);
    }

    private function addRelations(string $table)
    {
        if (isset($this->mappingTable[$table]['relations'])) {
            devoLog($this->mappingTable[$table]['relations'], "ana tablodaki ilişkili tabloar", $this->mainTable . '.sql');

            $relationDataMain = [];
            foreach ($this->mappingTable[$table]['relations'] as $relationName => $relation) {
                devoLog([$relationName, $relation], "ilişki yapılacak tablo ve keyleri", $this->mainTable . '.sql');

                $alias = "{$relation['related_table']}_{$relationName}";
                devoLog($alias, "hint", $this->mainTable . '.sql');

                // Sütunları seç
                $relSelectCols = [];
                foreach ($this->mappingTable[$relation['related_table']]['columns'] as $column) {
                    $relSelectCols[] = "{$alias}.{$column} AS {$relation['related_table']}_{$column}";
                }
                devoLog($relSelectCols, "hint", $this->mainTable . '.sql');

                // JOIN ifadesini oluştur
                $this->joinQueries = "LEFT JOIN {$relation['related_table']} AS {$alias} ON {$table}.{$relation['foreign_key']} = {$alias}.{$relation['local_key']}";

                // İlişkili tablonun ilişkilerini ekle
                // $this->addRelations($relation['related_table']);

                $selectQueryRel = implode(", ", $relSelectCols);
                $joinQuery = $this->joinQueries;
                $sql = "SELECT $selectQueryRel FROM {$this->mainTable} $joinQuery";
                devoLog($sql, "hint", $this->mainTable . '.sql');

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $res = $stmt->fetchAll(PDO::FETCH_ASSOC);


                $relMainData = [];
                $relMainData[$relationName] = $res;
                $relationDataMain[] = $relMainData;
            }

            $this->relationData = $relationDataMain;
        }
    }

    private function getRelations(string $table, $relationName, $parent_id)
    {
        // devoLog(1, "hint", $this->mainTable . '.sql');
        if (isset($this->mappingTable[$table]['relations'][$relationName])) {

            $relationDataMain = [];
            $relation = $this->mappingTable[$table]['relations'][$relationName];

            $alias = "{$relation['related_table']}_{$relationName}";

            // Sütunları seç
            $relSelectCols = [];
            foreach ($this->mappingTable[$relation['related_table']]['columns'] as $column) {
                $relSelectCols[] = "{$alias}.{$column} AS {$relation['related_table']}_{$column}";
            }


            // JOIN ifadesini oluştur
            $this->joinQueries = "LEFT JOIN {$relation['related_table']} AS {$alias} ON {$table}.{$relation['foreign_key']} = {$alias}.{$relation['local_key']}";

            // İlişkili tablonun ilişkilerini ekle
            // $this->addRelations($relation['related_table']);

            $selectQueryRel = implode(", ", $relSelectCols);
            $joinQuery = $this->joinQueries;
            $sql = "SELECT $selectQueryRel FROM {$this->mainTable} $joinQuery WHERE {$table}.id = $parent_id";


            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $res;
        }
    }

    public function getSQL(): string
    {
        devoLog(1, "hint", $this->mainTable . '.sql');
        $selectQuery = implode(', ', $this->selectColumns);
        // $joinQuery = implode(' ', $this->joinQueries);
        return "SELECT $selectQuery FROM {$this->mainTable}";
    }

    public function execute(): array
    {
        devoLog(1, "hint", $this->mainTable . '.sql');
        $sql = $this->getSQL();
        $stmt = $this->pdo->prepare("
        
        SELECT
    limited_users.user_id,
    limited_users.user_name,
    limited_users.user_email,
    posts.id AS relation_post_id,
    posts.title AS relation_post_title,
    posts.content AS relation_post_content,
    comments.id AS relation_comment_id,
    comments.content AS relation_comment_content,
    comment_users.id AS relation_comment_user_id,
    comment_users.name AS relation_comment_user_name,
    comment_users.email AS relation_comment_user_email,
    related_comment_users.id AS relation_comment_relatinon_user_id,
    related_comment_users.name AS relation_comment_relatinon_user_name,
    related_comment_users.email AS relation_comment_relatinon_user_email
FROM
    (
        SELECT
            users.id AS user_id,
            users.name AS user_name,
            users.email AS user_email
        FROM
            users
        LIMIT 10
    ) AS limited_users
LEFT JOIN posts ON posts.user_id = limited_users.user_id
LEFT JOIN comments ON comments.post_id = posts.id
LEFT JOIN users AS comment_users ON comment_users.id = comments.user_id
LEFT JOIN users AS related_comment_users ON related_comment_users.id = comments.user_id;  -- Ekstra bir JOIN

");
        $stmt->execute();
        $resMain = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $resMain;


        if (isset($resMain) && count($resMain) > 0) {

            $tableNamePre = explode('_', array_key_first($resMain[0]));
            $tableName = $tableNamePre[0];

            if (isset($this->mappingTable[$tableName]['relations'])) {

                foreach ($resMain as $key => $value) {

                    foreach ($this->mappingTable[$tableName]['relations'] as $keyRel => $valueRel) {
                        $relationName = $keyRel;
                        $resMain[$key][$relationName] = $this->getRelations($tableName, $relationName, $value[$tableName . '_id']);
                    }
                }
            }
            return $resMain;
        }



        //test
        // return $this->getRelations('posts', 'comment', 1);
    }
}
