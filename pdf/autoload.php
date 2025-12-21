<?php

/**
 * OFAST Certificate - Unified Dompdf Autoloader
 * Loads Dompdf classes and minimal dependency stubs
 */

// Load stub files first (they define namespaces for dependencies)
$stubPath = __DIR__ . '/stubs/';
require_once $stubPath . 'FontLib.php';
require_once $stubPath . 'SvgLib.php';
require_once $stubPath . 'Html5.php';

// Dompdf base path - using dompdf-2.0.4
$dompdfPath = __DIR__ . '/dompdf-2.0.4/dompdf-2.0.4/';

// Register autoloader for Dompdf namespace
spl_autoload_register(function ($class) use ($dompdfPath) {
    // Only handle Dompdf namespace
    if (strpos($class, 'Dompdf\\') !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen('Dompdf\\'));
    $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    // Check src folder first
    $file = $dompdfPath . 'src' . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Check lib folder (for Cpdf class)
    $file = $dompdfPath . 'lib' . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});
