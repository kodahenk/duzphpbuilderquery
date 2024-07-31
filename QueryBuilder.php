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
        devoLog($this->mainTable);
        $this->pdo = $pdo; // PDO nesnesini al
        $this->buildQuery();
    }

    private function buildQuery()
    {
        // Ana tablonun sütunlarını seç
        foreach ($this->mappingTable[$this->mainTable]['columns'] as $column) {
            $this->selectColumns[] = "{$this->mainTable}.{$column} AS {$this->mainTable}_{$column}";
        }

        devoLog($this->selectColumns);
        // İlişkileri ekle
        $this->addRelations($this->mainTable);
    }

    private function addRelations(string $table)
    {
        if (isset($this->mappingTable[$table]['relations'])) {
            devoLog($this->mappingTable[$table]['relations']);

            $relationDataMain = [];
            foreach ($this->mappingTable[$table]['relations'] as $relationName => $relation) {
                devoLog([$relationName, $relation]);

                $alias = "{$relation['related_table']}_{$relationName}";
                devoLog($alias);

                // Sütunları seç
                $relSelectCols = [];
                foreach ($this->mappingTable[$relation['related_table']]['columns'] as $column) {
                    $relSelectCols[] = "{$alias}.{$column} AS {$relation['related_table']}_{$column}";
                }
                devoLog($relSelectCols);

                // JOIN ifadesini oluştur
                $this->joinQueries = "LEFT JOIN {$relation['related_table']} AS {$alias} ON {$table}.{$relation['foreign_key']} = {$alias}.{$relation['local_key']}";

                // İlişkili tablonun ilişkilerini ekle
                // $this->addRelations($relation['related_table']);

                $selectQueryRel = implode(", ", $relSelectCols);
                $joinQuery = $this->joinQueries;
                $sql = "SELECT $selectQueryRel FROM {$this->mainTable} $joinQuery";
                devoLog($sql);

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
        // devoLog();
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
        devoLog();
        $selectQuery = implode(', ', $this->selectColumns);
        // $joinQuery = implode(' ', $this->joinQueries);
        return "SELECT $selectQuery FROM {$this->mainTable}";
    }

    public function execute(): array
    {
        devoLog();
        $sql = $this->getSQL();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $resMain = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
        return $this->getRelations('posts', 'comment', 1);
    }
}
