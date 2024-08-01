<?php

function devoLog(mixed $param = '', string $hint = '', string $filename = 'sql.log'): void
{
    static $isFirstCall = true; // İlk çağrıda true, sonraki çağrılarda false

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
    $timestamp = date('H:i:s Y.m.d');

    // Get the file and line where this function was called
    $backtrace = debug_backtrace();

    $callerFile = $backtrace[0]['file'] ?? 'unknown file';
    $callerLine = $backtrace[0]['line'] ?? 'unknown line';
    $callerFunction = $backtrace[1]['function'] ?? 'unknown function';
    $callerClass = $backtrace[1]['class'] ?? 'unknown class';
    $callerArgs = $backtrace[1]['args'] ?? 'unknown args'; 

    $relativeCallerFile = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $callerFile);

    $logEntry = '';
    // Check if the file is new or empty and add a separator at the top if so
    if ($isFirstCall) {
        // dosya varsa sil
        if (file_exists($filename)) {
            unlink($filename);
        }
        
        $logEntry = "<<<<<<<<<<<<<<<<<<<<<<<<<<< START >>>>>>>>>>>>>>>>>>>>>>>>>>>\n==================== $timestamp ====================\n";
    }
    $isFirstCall = false;

    if (is_array($callerArgs)) {
        $callerArgs = json_encode($callerArgs, JSON_PRETTY_PRINT);
    }

    // Format the log entry
$logEntry .= 
"[HINT]:$hint
[FILE]:$relativeCallerFile:$callerLine
[FUNC]:$callerFunction
[CLSS]:$callerClass
[ARGS]:$callerArgs
[DATA]:$logContent
==================== $timestamp ====================";

    // Write the log content to the file
    file_put_contents($filename, $logEntry, FILE_APPEND);
}
