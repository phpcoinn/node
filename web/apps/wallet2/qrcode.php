<?php

require_once __DIR__ ."/inc/phpqrcode.php";

QRcode::png($_GET['address'],$outfile = false, $level = QR_ECLEVEL_L, $size = 10);
