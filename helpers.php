<?php

function devoLog(mixed $param = '', string $filename = 'sql.log'): void
{
    // Extract the directory path from the filename
    $directory = dirname($filename) . '/logs';
    $filename = $directory . '/' . basename($filename);

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
    // $callerFunction = $backtrace[0]['function'] ?? 'unknown function';
    // $callerClass = $backtrace[0]['class'] ?? 'unknown class';

    $relativeCallerFile = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $callerFile);

    // Format the log entry
    $logEntry = "[DATE]:$timestamp\n[FILE]:$relativeCallerFile:$callerLine\n$logContent\n====================================================\n";

    // Check if the file is new or empty and add a separator at the top if so
    if (!file_exists($filename) || filesize($filename) === 0) {
        $logEntry = "====================================================\n" . $logEntry;
    }
   
    // Write the log content to the file
    file_put_contents($filename, $logEntry, FILE_APPEND);
}
