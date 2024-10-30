<?php

function humanDiff($amount) {
    $sign = $amount == 0 ? "" : ($amount > 0 ? "+" : "-");
    if($amount < 1000) {
        return $sign . round($amount, 2);
    } else if ($amount < 1000000) {
        return $sign .round($amount / 1000, 2)."k";
    } else {
        return $sign .round($amount / 1000000, 2)."m";
    }
}

function colorDiff($amount) {
    if($amount == 0) return "warning";
    if($amount < 0) return "danger";
    if($amount > 0) return "success";
}

function getHistoryData($address, $height) {
    global $db;
    $sql="select -sum(t.val + t.fee) as val, count(t.id) as cnt from transactions t where t.src = ? and t.height < ?";
    $row = $db->row($sql, [$address, $height], false);
    $val1 = $row['val'];
    $cnt1 = $row['cnt'];
    $sql="select sum(t.val) as val, count(t.id) as cnt, sum(if(t.type=0, t.val, 0)) as reward
            from transactions t where t.dst = ? and t.height < ?";
    $row = $db->row($sql, [$address, $height], false);
    $val2 = $row['val'];
    $cnt2 = $row['cnt'];
    $reward = floatval($row['reward']);
    return [
        "balance"=>floatval($val1 + $val2),
        "transactions"=>$cnt1 + $cnt2,
        "reward"=>$reward
    ];
}
