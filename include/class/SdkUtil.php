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
        $tx = self::createAndSignTx($node, $private_key, $dst, $val, $type);
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



}
