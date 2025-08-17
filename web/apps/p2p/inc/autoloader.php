<?php

require 'phar://' . dirname(__DIR__) . '/vendor.phar/autoload.php';
require __DIR__ . "/functions.php";

spl_autoload_register(function ($class) {
    // Convert namespace to directory structure
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/class/' . $path . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
