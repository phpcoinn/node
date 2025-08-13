<?php

interface AssetService
{
    public function getEscrowPrivateKey();

    public function createCancelTx($offer_id, $dst, $val);

    public function getStartBlockNumber();

    public function findTransfers($block_number);

    public function findTransaction(mixed $id);

    public function getConfirmations(string $type);

    public function createPayment(mixed $amount, mixed $toAddress, $offer);

    public function addressLink(string $address);

    public function txLink(mixed $txId);

    public function getMaxTradeFee();

    public function depositFromWallet(mixed $amount, $offer);

    public function depositFromWalletCallback(mixed $offer, $data);

    public function transferFromWallet(mixed $amount, mixed $offer);

    public function transferFromWalletCallback(mixed $offer, $data);

    public function getEscrowAddress();

    public function getDecimals();

    public function checkAddress(mixed $address);

    public function getLastHeight();

    public function checkTransaction($tx);

    public function resendTx(string $txId);
}