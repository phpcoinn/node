<?php

/**
 * Class Blacklist
 */
final class Blacklist
{
    /**
     * The official list of blacklisted public keys
     */
    public const PUBLIC_KEYS = [
        // phpcs:disable Generic.Files.LineLength
        // phpcs:enable
	    // public_key => address
    ];

    /**
     * The official list of blacklisted addresses
     */
    public const ADDRESSES = [
        // phpcs:disable Generic.Files.LineLength
        // phpcs:enable
    ];

	public const IPS = [

	];

    /**
     * Check if a public key is blacklisted
     *
     * @param string $publicKey
     * @return bool
     */
    public static function checkPublicKey(string $publicKey): bool
    {
        return key_exists($publicKey, static::PUBLIC_KEYS);
    }

    /**
     * Check if an address is blacklisted
     *
     * @param string $address
     * @return bool
     */
    public static function checkAddress(string $address): bool
    {
        return key_exists($address, static::ADDRESSES);
    }

    static function checkIp($ip) {
    	if(count(self::IPS)==0 || !in_array($ip, self::IPS)) {
    		return true;
	    }
    	return false;
    }
}
