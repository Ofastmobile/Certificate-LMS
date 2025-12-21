<?php

/**
 * Dompdf Autoloader
 * Loads Dompdf classes from src and lib folders
 */

namespace Dompdf;

class Autoloader
{
    const PREFIX = 'Dompdf';

    private static $basePath;

    /**
     * Register the autoloader
     */
    public static function register()
    {
        self::$basePath = dirname(__DIR__);
        spl_autoload_register([new self, 'autoload']);
    }

    /**
     * Autoload function
     */
    public function autoload($class)
    {
        // Only handle Dompdf namespace
        if (strpos($class, self::PREFIX . '\\') !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen(self::PREFIX) + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        // Check lib folder first for Cpdf
        if ($relativeClass === 'Cpdf') {
            $file = self::$basePath . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Cpdf.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }

        // Check src folder
        $file = self::$basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        // Check lib folder for other classes
        $file = self::$basePath . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
}
