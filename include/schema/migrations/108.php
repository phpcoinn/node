<?php

global $db;

$sql='update transactions set data = null where data is not null;';
$db->run($sql);