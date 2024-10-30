<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit039bf9dca7ac723b723c07ac837a90be
{
    public static $files = array (
        'dfbc31af31c3b5cf20b2a5c72ff37b12' => __DIR__ . '/../..' . '/include/coinspec.inc.php',
        '660d3b94e3a06dc1db22e1a9b9639665' => __DIR__ . '/../..' . '/include/functions.inc.php',
        '9cd6408de7a4a9c0b99dfb3f989642c0' => __DIR__ . '/../..' . '/include/genesis.inc.php',
    );

    public static $prefixLengthsPsr4 = array (
        'J' => 
        array (
            'Jdenticon\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Jdenticon\\' => 
        array (
            0 => __DIR__ . '/..' . '/jdenticon/jdenticon/src',
        ),
    );

    public static $classMap = array (
        'Account' => __DIR__ . '/../..' . '/include/class/Account.php',
        'Api' => __DIR__ . '/../..' . '/include/class/Api.php',
        'Blacklist' => __DIR__ . '/../..' . '/include/class/Blacklist.php',
        'Block' => __DIR__ . '/../..' . '/include/class/Block.php',
        'Blockchain' => __DIR__ . '/../..' . '/include/class/Blockchain.php',
        'Cache' => __DIR__ . '/../..' . '/include/class/Cache.php',
        'CommonSessionHandler' => __DIR__ . '/../..' . '/include/class/CommonSessionHandler.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Config' => __DIR__ . '/../..' . '/include/class/Config.php',
        'Cron' => __DIR__ . '/../..' . '/include/class/Cron.php',
        'Dapps' => __DIR__ . '/../..' . '/include/class/Dapps.php',
        'Forker' => __DIR__ . '/../..' . '/include/class/Forker.php',
        'Masternode' => __DIR__ . '/../..' . '/include/class/Masternode.php',
        'Mempool' => __DIR__ . '/../..' . '/include/class/Mempool.php',
        'Miner' => __DIR__ . '/../..' . '/include/class/Miner.php',
        'NodeMiner' => __DIR__ . '/../..' . '/include/class/NodeMiner.php',
        'NodeSync' => __DIR__ . '/../..' . '/include/class/NodeSync.php',
        'Nodeutil' => __DIR__ . '/../..' . '/include/class/Nodeutil.php',
        'Peer' => __DIR__ . '/../..' . '/include/class/Peer.php',
        'PeerPost' => __DIR__ . '/../..' . '/include/class/PeerPost.php',
        'PeerRequest' => __DIR__ . '/../..' . '/include/class/PeerRequest.php',
        'Propagate' => __DIR__ . '/../..' . '/include/class/Propagate.php',
        'SdkUtil' => __DIR__ . '/../..' . '/include/class/SdkUtil.php',
        'SmartContract' => __DIR__ . '/../..' . '/include/class/SmartContract.php',
        'SmartContractBase' => __DIR__ . '/../..' . '/include/class/sc/SmartContractBase.php',
        'SmartContractContext' => __DIR__ . '/../..' . '/include/class/sc/SmartContractWrapper.php',
        'SmartContractEngine' => __DIR__ . '/../..' . '/include/class/SmartContractEngine.php',
        'SmartContractMap' => __DIR__ . '/../..' . '/include/class/sc/SmartContractMap.php',
        'SmartContractWrapper' => __DIR__ . '/../..' . '/include/class/sc/SmartContractWrapper.php',
        'Sync' => __DIR__ . '/../..' . '/include/class/Sync.php',
        'Task' => __DIR__ . '/../..' . '/include/class/Task.php',
        'Transaction' => __DIR__ . '/../..' . '/include/class/Transaction.php',
        'Util' => __DIR__ . '/../..' . '/include/class/Util.php',
        'Wallet' => __DIR__ . '/../..' . '/include/class/Wallet.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit039bf9dca7ac723b723c07ac837a90be::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit039bf9dca7ac723b723c07ac837a90be::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit039bf9dca7ac723b723c07ac837a90be::$classMap;

        }, null, ClassLoader::class);
    }
}
