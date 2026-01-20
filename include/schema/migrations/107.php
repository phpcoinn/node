<?php

global $db;

$sql='update transactions set data = null;';
$db->run($sql);