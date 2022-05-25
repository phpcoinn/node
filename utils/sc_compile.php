<?php

define("ROOT", dirname(__DIR__));
try {
	$file = $argv[1];
	$phar_file = $argv[2];

	$sc_dir = ROOT . "/tmp/sc";

	if(!file_exists($sc_dir)) {
		@mkdir($sc_dir);
		@chmod($sc_dir, 0777);
	}


	if(empty($phar_file)) {
		$name = md5($file);
		$phar_file = $sc_dir . "/$name.phar";
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
	} else {
		$defaultStub = $phar->createDefaultStub(basename($file));
		$phar->addFile($file, basename($file));
	}
	$phar->setStub($defaultStub);
	$phar->stopBuffering();
//	$phar->compressFiles(Phar::GZ);

	chmod($phar_file, 0777);

	echo $phar_file;
} catch (Exception $e) {
	echo $e->getMessage();
}
