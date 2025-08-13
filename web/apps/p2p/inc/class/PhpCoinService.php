<?php

class PhpCoinService implements AssetService
{

    const PHP_ESCROW_ADDRESS = "PY5DYYC6Kj7npy1tyMnQP3TeipbHdxCx6f";
    const START_BLOCK_NUMBER = 1070000;

    public function getEscrowPrivateKey()
    {
        global $_config;
        return $_config["php_escrow"]["private_key"];
    }

    public function createCancelTx($offer_id, $dst, $val)
    {
        $public_key = priv2pub(self::getEscrowPrivateKey());
        $msg = json_encode([
            "app"=>"p2p-trader",
            "action"=>"cancelOffer",
            "offer"=>$offer_id,
        ]);
        $tx = new Transaction($public_key, $dst, $val, TX_TYPE_SEND, time(), $msg);
        $tx->sign(self::getEscrowPrivateKey());
        $tx_id= $tx->addToMemPool($err);
        return $tx_id;
    }

    public function getStartBlockNumber()
    {
        return self::START_BLOCK_NUMBER;
    }

    public function findTransfers($block_number)
    {
        global $db;
        $sql='select * from transactions t where t.type = ? and t.dst = ? and t.height >= ? order by t.height asc';
        $txs = $db->run($sql, [TX_TYPE_SEND, self::PHP_ESCROW_ADDRESS, $block_number], false);
        $txIds = [];
        foreach ($txs as $tx) {
            $txIds[] = $tx['id'];
        }
        return $txIds;
    }

    public function findTransaction($id)
    {
        $tx = Transaction::get_transaction($id);
        if(!$tx) {
            sleep(3);
            $tx = Transaction::get_mempool_transaction($id);
        }
        $src = Account::getAddress($tx['public_key']);
        return [
            "id" => $id,
            "from" => $src,
            "to" => $tx['dst'],
            "amount" => $tx['val'],
            "height" => $tx['height'],
        ];
    }

    public function getConfirmations($type)
    {
        return match ($type) {
            'wait' => 1,
            default => 1,
        };
    }

    public function createPayment(mixed $amount, mixed $toAddress, $offer)
    {
        $public_key = priv2pub(self::getEscrowPrivateKey());
        $msg = json_encode([
            "app"=>"p2p-trader",
            "action"=>"return",
            "offer"=>$offer['id'],
            "accept_tx_id"=>$offer['accept_tx_id']
        ]);
        $tx = new Transaction($public_key, $toAddress, $amount, TX_TYPE_SEND, time(), $msg);
        $tx->sign(self::getEscrowPrivateKey());
        return $tx->addToMemPool($err);
    }

    public function addressLink(string $address)
    {
        return "/apps/explorer/address.php?address=$address";
    }

    public function txLink(mixed $txId)
    {
        return "/apps/explorer/tx.php?id=$txId";
    }

    public function getMaxTradeFee()
    {
        return 100 / pow(10, 8);
    }

    public function depositFromWallet(mixed $amount, $offer)
    {
        $msg = json_encode([
            "app"=>"p2p-trader",
            "action"=>"depositFromWallet",
            "offer"=>$offer["id"],
        ]);
        $transaction = [
            "val"=>$offer['base_amount'] + $offer['base_dust_amount'],
            "fee"=>0,
            "dst"=>self::PHP_ESCROW_ADDRESS,
            "src"=>OfferService::userAddress(),
            "msg"=>$msg,
            "type"=>TX_TYPE_SEND,
            "date"=>time()
        ];
        $transaction = base64_encode(json_encode($transaction));
        $redirect = AppView::BASE_URL . "?callback=depositFromWalletCallback";
        $url = "/dapps.php?url=".AppView::GATEWAY_DAPP_ID."/gateway/approve.php?app=".AppView::APP_NAME."&tx=$transaction&redirect=$redirect";
        Pajax::redirect($url);
    }

    public function depositFromWalletCallback(mixed $offer, $data)
    {
        if(isset($data['res'])) {
            $deposit_tx_id = $data['res'];
            $offer_id = $offer['id'];
            OfferService::setOfferDepositing($offer_id, $deposit_tx_id);
        }
        Pajax::redirect(AppView::BASE_URL);
    }

    public function transferFromWallet(mixed $amount, mixed $offer)
    {
        $msg = json_encode([
            "app"=>"p2p-trader",
            "action"=>"transferFromWallet",
            "offer"=>$offer["id"],
        ]);
        $transaction = [
            "val"=>$offer['base_amount'] + $offer['base_dust_amount'],
            "fee"=>0,
            "dst"=>self::PHP_ESCROW_ADDRESS,
            "src"=>OfferService::userAddress(),
            "msg"=>$msg,
            "type"=>TX_TYPE_SEND,
            "date"=>time()
        ];
        $transaction = base64_encode(json_encode($transaction));
        $redirect = AppView::BASE_URL . "?callback=transferFromWalletCallback";
        $url = "/dapps.php?url=".AppView::GATEWAY_DAPP_ID."/gateway/approve.php?app=".AppView::APP_NAME."&tx=$transaction&redirect=$redirect";
        Pajax::redirect($url);
    }

    public function transferFromWalletCallback(mixed $offer, $data)
    {
        if(isset($data['res'])) {
            $accept_tx_id = $data['res'];
            $offer_id = $offer['id'];
            if($offer_id) {
                OfferService::setAcceptedOfferTransferring($offer_id, $accept_tx_id);
            }
        }
        Pajax::redirect(AppView::BASE_URL);
    }

    public function getEscrowAddress()
    {
        return self::PHP_ESCROW_ADDRESS;
    }

    public function getDecimals()
    {
        return 8;
    }

    public function checkAddress(mixed $address)
    {
        return Account::valid($address);
    }

    public function getLastHeight()
    {
        return Block::getHeight();
    }

    public function checkTransaction($tx)
    {
        return true;
    }

    public function resendTx(string $txId)
    {
        throw new \Exception("Not implemented");
    }
}