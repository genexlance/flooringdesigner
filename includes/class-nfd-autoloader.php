<?php
/**
 * Simple autoloader for Nano-Floor Designer classes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Autoloader
{
    public static function init(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        if (strpos($class, 'NFD_') !== 0) {
            return;
        }
        $filename = strtolower(str_replace('_', '-', $class));
        $file = NFD_PLUGIN_DIR . 'includes/class-' . $filename . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
