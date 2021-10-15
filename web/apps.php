<?php

require_once dirname(__DIR__).'/include/init.inc.php';
require_once ROOT."/web/apps/apps.inc.php";

$repoServer = isRepoServer();
if(!$repoServer) {
	exit;
}

$file = ROOT . "/tmp/apps.tar.gz";
if(!file_exists($file)) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
readfile($file);
