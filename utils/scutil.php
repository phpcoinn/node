<?php
if(php_sapi_name() !== 'cli') exit;
@define("DEFAULT_CHAIN_ID", file_get_contents(dirname(__DIR__)."/chain_id"));
@define("ROOT", dirname(__DIR__));
require_once dirname(__DIR__).'/vendor/autoload.php';

class SCUtil {

    static function compileContract($source_file, $compiled_file)
    {
        $res = SmartContract::compile($source_file, $compiled_file, $error);
        if(!$res) {
            throw new Exception($error);
        }
    }

    static function generateDeployTx($phar_file, $private_key, $sc_address, $amount=0, $params=[], $name=null, $description=null, $metadata=null) {


        $phar_code = file_get_contents($phar_file);
        $code = base64_encode($phar_code);

        $interface = SmartContractEngine::verifyCode($code, $error, $sc_address);

        if(empty($metadata)) {
            $metadata = [];
            if(!empty($name)) {
                $metadata['name']=$name;
            }
            if(!empty($description)) {
                $metadata['description']=$description;
            }
        }

        $deploy_data=[
            "code"=>$code,
            "amount"=>num($amount),
            "params"=>$params,
            "interface"=>$interface,
            "metadata"=>$metadata,
        ];
        $text = base64_encode(json_encode($deploy_data));
        $sc_signature = ec_sign($text, $private_key);
        $public_key = priv2pub($private_key);

        $tx =  Transaction::generateSmartContractDeployTx($code, $sc_signature, $public_key, $sc_address, $amount, $params, $metadata);
        return $tx;
    }

    static function getInterface($node, $sc_address){
        $url="$node/api.php?q=getSmartContractInterface&address=$sc_address";
        $res = self::api_get($url);
        if($res['status']!="ok") {
            throw new Exception($res['data']);
        }
        return $res['data'];
    }

    static function sendTx($node, Transaction $tx) {
        $res = self::api_post($node."/api.php?q=sendTransaction" , ["tx"=>base64_encode(json_encode($tx->toArray()))]);
        if($res['status']!="ok") {
            throw new Exception($res['data']);
        }
        return $res['data'];
    }

    private static function api_post($url, $data = [], $timeout = 60, $debug = false) {
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
        return $res;
    }

    private static function api_get($url, &$error = null) {
        $res = @file_get_contents($url);
        $res = json_decode($res, true);
        return $res;
    }

    static function generateScExecTx($private_key, $sc_address, $method, $amount=0, $params=[]) {
        $public_key = priv2pub($private_key);
        $tx=Transaction::generateSmartContractExecTx($public_key, $sc_address, $method, $amount, $params);
        return $tx;
    }

    static function generateScSendTx($sc_private_key, $dst_address, $method, $amount=0, $params=[]) {
        $sc_public_key = priv2pub($sc_private_key);
        $tx = Transaction::generateSmartContractSendTx($sc_public_key, $dst_address, $method, $amount, $params);
        return $tx;
    }

    static function executeView($node, $sc_address, $method, $params = []) {
        $url="$node/api.php?q=getSmartContractView&address=$sc_address&method=$method&params=".base64_encode(json_encode($params));
        $res = self::api_get($url);
        if($res['status']!="ok") {
            throw new Exception($res['data']);
        }
        return $res['data'];
    }

    static function getPropertyValue($node, $sc_address, $property, $key = null) {
        $url="$node/api.php?q=getSmartContractProperty&address=$sc_address&property=$property&key=$key";
        $res = self::api_get($url);
        if($res['status']!="ok") {
            throw new Exception($res['data']);
        }
        return $res['data'];
    }
}
