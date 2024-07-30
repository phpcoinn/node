<?php

define("ROOT", dirname(__DIR__));
try {
	$sc_address = $argv[1];
	$file = $argv[2];
	$phar_file = $argv[3];

	$sc_dir = ROOT . "/tmp/sc";

	if(!file_exists($sc_dir)) {
		@mkdir($sc_dir);
		@chmod($sc_dir, 0777);
	}


	if(empty($phar_file)) {
		$name = md5($file);
		$phar_file = $sc_dir . "/$sc_address.phar";
    }

	if (file_exists($phar_file)) {
		unlink($phar_file);
	}
	$phar = new Phar($phar_file);
	$phar->startBuffering();
	$defaultStub = $phar->createDefaultStub();
	if(is_dir($file)) {
		$defaultStub = $phar->createDefaultStub();
		$phar->buildFromDirectory($file);
        $index_file = $file . "/index.php";
        $content = file_get_contents($index_file);
        $phar->delete("index.php");
        $content = str_replace("class SmartContract extends", "class $sc_address extends", $content);
        $phar->addFromString("index.php", $content);
	} else {
        $defaultStub = $phar->createDefaultStub();
        $content = file_get_contents($file);
        $content = str_replace("class SmartContract extends", "class $sc_address extends", $content);
        $phar->addFromString("index.php", $content);
	}
	$phar->setStub($defaultStub);
	$phar->stopBuffering();
//	$phar->compressFiles(Phar::GZ);

	chmod($phar_file, 0777);

	echo $phar_file;
} catch (Exception $e) {
	echo $e->getMessage();
}
