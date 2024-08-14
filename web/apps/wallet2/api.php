<?php

require_once dirname(__DIR__) . "/apps.inc.php";
require_once ROOT . '/web/apps/explorer/include/functions.php';

$q=$_GET['q'];


if($q == "fetchBalances") {
    $input = file_get_contents("php://input");
    $post = json_decode($input, true);
    $addresses = $post["addresses"];
    array_walk($addresses, function (&$item) {
        $item = "'$item'";
    });
    global $db;
    $addresses_list = implode(",", $addresses);
    $sql="select a.id, a.balance from accounts a where a.id in ($addresses_list)";
    $rows = $db->run($sql);
    $balances = [];
    foreach($rows as $row) {
        $balance = $row["balance"];
        $balances[$row["id"]] = $balance;
    }
    api_echo($balances);
}

if($q == "setAddresses") {
    session_start();
    $data = file_get_contents("php://input");
    $post =json_decode($data, true);
    $addresses = $post['addresses'];
    $_SESSION['logged']=true;
    $_SESSION['wallet']['addresses']=$addresses;
    api_echo("OK");
}

if($q == "setCurrentAddress") {
    session_start();
    $data = file_get_contents("php://input");
    $post =json_decode($data, true);
    $address = $post['address'];
    $_SESSION['logged']=true;
    $_SESSION['currentAddress']=$address;
    api_echo("OK");
}
