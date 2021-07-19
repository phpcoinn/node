<?php

try
{
	$srcDir = dirname(__DIR__);
	$outDir = dirname(__DIR__).DIRECTORY_SEPARATOR."dist";
	$pharFile = $outDir . DIRECTORY_SEPARATOR . 'wallet.phar';

	// clean up
	if (file_exists($pharFile))
	{
		unlink($pharFile);
	}

	// create phar
	$phar = new Phar($pharFile);

	// start buffering. Mandatory to modify stub to add shebang
	$phar->startBuffering();

	// Create the default stub from main.php entrypoint
	$defaultStub = $phar->createDefaultStub('wallet.php');

	$content = file_get_contents($srcDir. "/utils/wallet.php");

	$phar->addFromString('wallet.php', $content);

	// Add the rest of the apps files
	$list = $phar->buildFromDirectory($srcDir,'/'.str_replace(DIRECTORY_SEPARATOR, "\\".DIRECTORY_SEPARATOR, $srcDir).'\\'.DIRECTORY_SEPARATOR.'vendor.*/');
//	print_r($list);
	$list = $phar->buildFromDirectory($srcDir,'/'.str_replace(DIRECTORY_SEPARATOR, "\\".DIRECTORY_SEPARATOR, $srcDir).'\\'.DIRECTORY_SEPARATOR.'include.*/');
//	print_r($list);

//	echo $defaultStub;
	// Customize the stub to add the shebang
	$stub = "#!/usr/bin/env php \n" . $defaultStub;

	// Add the stub
	$phar->setStub($stub);

	$phar->stopBuffering();

	// plus - compressing it into gzip
	$phar->compressFiles(Phar::GZ);

	# Make the file executable
	chmod($pharFile, 0770);

	rename($pharFile, $outDir . DIRECTORY_SEPARATOR . 'phpcoin-wallet');

	echo "phpcoin-wallet successfully created" . PHP_EOL;
}
catch (Exception $e)
{
	echo $e->getMessage();
}
