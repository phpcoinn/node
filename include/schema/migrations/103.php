<?php

global $db;

$sql='INSERT INTO transaction_data (tx_id, data)
SELECT id, data
FROM transactions
WHERE data IS NOT NULL;';
$db->run($sql);
