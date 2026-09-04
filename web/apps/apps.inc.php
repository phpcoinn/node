<?php

require_once dirname(dirname(__DIR__)) . '/include/init.inc.php';


global $_config;
$nodeScore = round((float)($_config['node_score'] ?? 0), 2);

