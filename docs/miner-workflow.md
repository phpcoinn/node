# Miner Workflow

### Get Mining Information
Retrieves the current block height, difficulty, and other necessary data from the node.
* **File:** `include/class/Miner.php`
* **Function:** `getMiningInfo()`

### Start Mining Loop
The main loop that continuously attempts to find a new block.
* **File:** `include/class/Miner.php`
* **Function:** `start()`

### Calculate Elapsed Time
Calculates the time elapsed since the last block's timestamp.
* **File:** `include/class/Miner.php`
* **Function:** `start()` (inside the `while (!$blockFound)` loop)

### Calculate Argon2 Hash
Computes the Argon2i hash based on the elapsed time.
* **File:** `include/class/Block.php`
* **Function:** `calculateArgonHash()`

### Calculate Nonce
Derives a nonce by hashing the Argon2 hash and other block details.
* **File:** `include/class/Block.php`
* **Function:** `calculateNonce()`

### Calculate Hit
Calculates the 'hit' value based on the miner's address and the derived nonce.
* **File:** `include/class/Block.php`
* **Function:** `calculateHit()`

### Calculate Target
Calculates the dynamic target value based on the current difficulty and elapsed time.
* **File:** `include/class/Block.php`
* **Function:** `calculateTarget()`

### Check for a Valid Block
Compares the 'hit' and 'target' values to determine if a valid block has been found.
* **File:** `include/class/Miner.php`
* **Function:** `start()` (inside the `while (!$blockFound)` loop, the condition `$hit > $target`)

### Check for New Blocks from Server
Periodically checks with the node to see if a new block has already been found by another miner.
* **File:** `include/class/Miner.php`
* **Function:** `getMiningInfo()` (called within the mining loop)

### Submit Block
If a valid block is found, it is submitted to the node for verification.
* **File:** `include/class/Miner.php`
* **Function:** `sendHash()`
