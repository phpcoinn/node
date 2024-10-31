<?php

require_once dirname(__DIR__) . "/apps.inc.php";
require_once ROOT . '/web/apps/explorer/include/functions.php';
require_once './inc/functions.php';

$q=$_GET['q'];

function getCoinPrice() {
    $res = file_get_contents("https://main1.phpcoin.net/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/api.php?q=coinInfo");
    $res = json_decode($res, true);
    $usdPrice = num($res['usdPrice'], 6);
    return $usdPrice;
}

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

if($q=="getWalletRewards") {
    $period = $_GET["period"];
    $address = $_GET["address"];

    $usdPrice = getCoinPrice();

    $height = Block::getHeight();

    $where = "";
    $params = [$address];
    if($period == '1W') {
        $where = " and t.height > ? ";
        $params[]=$height - 1440 * 7;
    } else if ($period == '1M') {
        $where = " and t.height > ? ";
        $params[]=$height - 1440 * 30;
    } else if ($period == '1Y') {
        $where = " and t.height > ? ";
        $params[]=$height - 1440 * 365;
    }

    $sql="select t.message, sum(t.val) as val
        from transactions t
        where t.dst = ? and t.type = 0 $where
        group by t.message";
    $rows = $db->run($sql, $params, false);
    $data=[];
    foreach($rows as $row) {
        $data[]=[
            "type"=>ucfirst($row['message']),
            "amount"=>floatval($row['val']),
            "usdValue"=>$row['val'] * $usdPrice
        ];
    }
    usort($data, function($a, $b) {
        return $b['amount'] - $a['amount'];
    });
    api_echo($data);
}

if($q =="getWalletMnRoi") {
    $period = $_GET["period"];
    $address = $_GET["address"];
    $usdPrice = getCoinPrice();
    $info = Account::getAddressInfo($address);
    $rewardStat = Transaction::getMasternodeRewardsStat($address);
    $txStat = Transaction::getTxStatByType($address, "masternode");
    $data=[];
    $data['mn_count'] = count($info['masternodes']);
    $height = Block::getHeight();
    $locked = $data['mn_count']*Block::getMasternodeCollateral($height);
    $data['locked']=humanAmount($locked);
    $data['earned']=humanAmount($txStat['total']);
    $data['earned_usd']=round($txStat['total']*$usdPrice,2);
    $data['locked_usd']=round($locked*$usdPrice,2);

    $data['daily']['earned']=humanAmount($rewardStat['total']['daily']);
    $data['daily']['usd']=round($rewardStat['total']['daily']*$usdPrice,2);
    $data['daily']['roi']=round(100 * $rewardStat['total']['daily'] / $locked,2);
    $data['weekly']['earned']=humanAmount($rewardStat['total']['weekly']);
    $data['weekly']['usd']=round($rewardStat['total']['weekly']*$usdPrice,2);
    $data['weekly']['roi']=round(100 * $rewardStat['total']['weekly'] / $locked,2);
    $data['monthly']['earned']=humanAmount($rewardStat['total']['monthly']);
    $data['monthly']['usd']=round($rewardStat['total']['monthly']*$usdPrice,2);
    $data['monthly']['roi']=round(100 * $rewardStat['total']['monthly'] / $locked,2);
    $data['yearly']['earned']=humanAmount($rewardStat['total']['yearly']);
    $data['yearly']['usd']=round($rewardStat['total']['yearly']*$usdPrice,2);
    $data['yearly']['roi']=round(100 * $rewardStat['total']['yearly'] / $locked,2);


    api_echo($data);
}