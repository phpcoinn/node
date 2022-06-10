<?php
require_once dirname(__DIR__).'/include/init.inc.php';

if(isset($_GET['url'])) {
	Dapps::render();
} else if (isset($_GET['download'])) {
	Dapps::download();
}


