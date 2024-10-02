<?php

class SdkUtil
{
    static function api_get($node, $q, &$err = null)
    {
        $url = $node . "/api.php?q=$q";
        $res = url_get($url);
        $res = json_decode($res, true);
        if ($res['status'] != "ok") {
            $err = $res['data'];
            return false;
        } else {
            return $res['data'];
        }
    }

    static function api_post($url, $data = [], &$err=null) {
        $postdata = http_build_query(
            [
                'data' => json_encode($data)
            ]
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$postdata );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, DEVELOPMENT ? 0 : 2);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, !DEVELOPMENT);
        $result = curl_exec($ch);
        curl_close ($ch);
        $res = json_decode($result, true);
        if ($res['status'] != "ok") {
            $err = $res['data'];
            return false;
        } else {
            return $res['data'];
        }
    }

    static function createAndSendTx($node, $private_key, $dst, $val, $type, $msg=null, $chain_id = CHAIN_ID)
    {
        $tx = self::createAndSignTx($private_key, $dst, $val, $type, $msg, $chain_id);
        $res = self::api_post($node."/api.php?q=sendTransaction" , ["tx"=>base64_encode(json_encode($tx->toArray()))]);
        return $res;
    }

    static function createAndSignTx($private_key, $dst, $val, $type, $msg=null, $chain_id = CHAIN_ID)
    {
        $public_key = priv2pub($private_key);
        $tx = new Transaction($public_key, $dst, $val, $type, time(), $msg);
        $base = $tx->getSignatureBase();
        $signature = ec_sign($base, $private_key, $chain_id);
        $tx->signature = $signature;
        return $tx;
    }

    static function removeMasternode($node,$address,$private_key,$payout_address,$masternode,$chain_id = CHAIN_ID) {
        $txData=self::api_get($node, "generateMasternodeRemoveTx&address=".$address.
            "&payout_address=$payout_address&mn_address=".$masternode);
        $tx=Transaction::getFromArray($txData);
        $tx->publicKey = priv2pub($private_key);
        $base = $tx->getSignatureBase();
        $tx->signature = ec_sign($base, $private_key,$chain_id);
        $txArr = $tx->toArray();
        $res = self::api_post($node."/api.php?q=sendTransaction" , ["tx"=>base64_encode(json_encode($txArr))]);
        return $res;
    }


    static function createMasternode($node, $address, $private_key, $reward_address, $masternode,$chain_id = CHAIN_ID)
    {
        $txData = self::api_get($node, "generateMasternodeCreateTx&address=".$address."&mn_address=".$masternode.
            (!empty($reward_address) ? "&reward_address=".$reward_address : ""));
        $tx=Transaction::getFromArray($txData);
        $tx->publicKey = priv2pub($private_key);
        $base = $tx->getSignatureBase();
        $tx->signature = ec_sign($base, $private_key,"00");
        $txArr = $tx->toArray();
        $res = self::api_post($node."/api.php?q=sendTransaction" , ["tx"=>base64_encode(json_encode($txArr))]);
        return $res;
    }

}
