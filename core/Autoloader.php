<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);
    
    if (count($parts) > 1) {
        $parts[0] = strtolower($parts[0]);
    }
    
    $file = $base_dir . implode('/', $parts) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
