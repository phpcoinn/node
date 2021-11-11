<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/

// make sure it's not accessible in the browser
if (php_sapi_name() !== 'cli') {
    die("This should only be run as cli");
}

define("CLI_UTIL", true);

require_once dirname(__DIR__).'/include/init.inc.php';
$cmd = trim($argv[1]);




if(method_exists(Util::class, $cmd)) {
	call_user_func([Util::class, $cmd], $argv);
	return;
} else {
	if(!empty($cmd)) {
		$str = str_replace(' ', '', ucwords(str_replace('-', ' ', $cmd)));
		$str[0] = strtolower($str[0]);
	}
	if(!empty($str) && method_exists(Util::class, $str)) {
		call_user_func([Util::class, $str], $argv);
		return;
	} else {
		echo "Invalid command: $str\n";
		echo "Available commands:
clean                                       - Cleans the entire database
pop [<n>]                                   - Delete n (default 1) last blocks
block-time                                  - Shows the block time of the last 100 blocks
peer <peer>                                 - Creates a peering session with another node
current                                     - Prints the current block in var_dump
blocks <height> [<limit>]                   - Prints the id and the height of the blocks >= <height>, max 100 or <limit>
recheck-blocks                              - Recheck all the blocks to make sure the blockchain is correct
peers                                       - Prints all the peers and their status
mempool                                     - Prints the number of transactions in mempool
delete-peer <peer>                          - Removes a peer from the peerlist
recheck-peers                               - Check all saved peers
peers-block [<diff>]                        - Prints the current height of all the peers and shows. <diff> show only different height than current
balance <address|public_key>                - Prints the balance of an address or a public key
block <height|id>                           - Returns a specific block
check-address <address>                     - Checks a specific address for validity
get-address <public_key>                    - Converts a public key into an address
clean-blacklist                             - Delete blacklisted peers
compare-blocks <peer> [<limit>]             - Compare blocks with peer
compare-accounts <peer>                     - Compare accounts with peer
masternode-hash                             - Calculate masternode hash
accounts-hash                               - Calculate accounts hash
version                                     - Show node version
sendblock <height> <peer>                   - Send block to peer
recheck-external-blocks <peer> [<height>]   - Recheck blocks from <height> at <peer>
check-block <peer> <height>                 - Check block at peer
find-forked-block <peer>                    - Find forked block at peer
validate-public-key <public-key>            - Validates public key
rewards-scheme                              - Prints reward scheme by blocks
download-apps                               - Download and update apps from repository
verify-blocks                               - Verify all blocks in blockchain
exportchain [<file>]                        - Export blockchain to file
importchain <file> [<verify>]               - Import and verify blocks from file
clear-peers                                 - Clear peers database
";
	}
}
