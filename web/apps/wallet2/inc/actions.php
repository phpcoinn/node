<?php
if(!defined("PAGE")) exit;

$action=$_GET['action'];
if($action=="login") {
    session_start();
    $data = file_get_contents("php://input");
    $post =json_decode($data, true);
    $addresses = $post['addresses'];
    $_SESSION['logged']=true;
    $_SESSION['wallet']['addresses']=$addresses;
    api_echo("OK");
}


if($action == "logout") {
    session_start();
    session_destroy();
    header("location: /apps/wallet2");
}

function knum($val)
{
    $val = floatval($val);
    if ($val < 1000) {
        return round($val);
    } else {
        return round($val / 1000) . "k";
    }
}
