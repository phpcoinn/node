<?php
@define("ROOT", dirname(__DIR__));
require_once ROOT.'/include/class/sc/Compiler.php';





try {
    $sc_address = $argv[1];
    $file = $argv[2];
    $phar_file = $argv[3];
    $compile_dir = dirname($phar_file);
    if(!file_exists($compile_dir)) {
        mkdir($compile_dir);
    }
    $phar_file =  Compiler::compile($file, $sc_address, $phar_file);
	echo $phar_file;
} catch (Exception $e) {
	echo $e->getMessage();
}
