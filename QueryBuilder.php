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
        // Konfigürasyon dosyasını yükle
        $this->devoLog($mainTable);

        $this->mappingTable = require 'config/mapping.php';
        $this->mainTable = $mainTable;
        $this->pdo = $pdo; // PDO nesnesini al
        $this->buildQuery();
    }

    private function buildQuery()
    {
        // Ana tablonun sütunlarını seç
        foreach ($this->mappingTable[$this->mainTable]['columns'] as $column) {
            $this->selectColumns[] = "{$this->mainTable}.{$column} AS {$this->mainTable}_{$column}";
        }

        $this->devoLog($this->mappingTable[$this->mainTable]['relations']);

        // İlişkileri ekle
        $this->addRelations($this->mainTable);
    }

    private function addRelations(string $table)
    {
        if (isset($this->mappingTable[$table]['relations'])) {


            $relationDataMain = [];
            foreach ($this->mappingTable[$table]['relations'] as $relationName => $relation) {

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
                $sql = "SELECT $selectQueryRel FROM {$this->mainTable} $joinQuery";


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
        $selectQuery = implode(', ', $this->selectColumns);
        // $joinQuery = implode(' ', $this->joinQueries);
        return "SELECT $selectQuery FROM {$this->mainTable}";
    }

    public function execute(): array
    {
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

    function devoLog(mixed $param, string $filename = 'log.sql'): void
    {
        // Extract the directory path from the filename
        $directory = dirname($filename);

        // Create the directory path if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        // Determine the log content based on the type of $param
        if (is_array($param)) {
            $logContent = json_encode($param, JSON_PRETTY_PRINT);
        } else {
            $logContent = (string)$param;
        }

        // Get the current timestamp in the desired format
        $timestamp = date('H:i Y.m.d');

        // Get the file and line where this function was called
        $backtrace = debug_backtrace();
        $callerFile = $backtrace[0]['file'] ?? 'unknown file';
        $callerLine = $backtrace[0]['line'] ?? 'unknown line';
        $relativeCallerFile = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $callerFile);

        // Format the log entry
        $logEntry = "$timestamp\n$relativeCallerFile:$callerLine\n$logContent\n====================================================\n";

        // Check if the file is new or empty and add a separator at the top if so
        if (!file_exists($filename) || filesize($filename) === 0) {
            $logEntry = "====================================================\n" . $logEntry;
        }

        // Write the log content to the file
        file_put_contents($filename, $logEntry, FILE_APPEND);
    }
}
