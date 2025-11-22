# Miner Workflow

This document outlines the complete step-by-step workflow of the command-line miner.

## Initialization

### 1. Parse Command-Line Arguments & Config
The miner script is launched. It first checks for command-line arguments: `node`, `address`, `cpu`, `block_cnt`, and `threads`. It then checks for a `miner.conf` file in the current working directory and overrides any settings with values from the config file. Command-line arguments take the highest precedence.
*   **File:** [`utils/miner.php`](../utils/miner.php)

### 2. Validate Node and Address
Ensures that both a node URL and a miner address have been provided. It will exit with a usage message if they are missing.
*   **File:** [`utils/miner.php`](../utils/miner.php)

### 3. Verify Public Key
Makes an API call to the specified node to retrieve the public key associated with the miner's address. This also serves as a check that the node is reachable and the address is valid.
*   **File:** [`utils/miner.php`](../utils/miner.php)

### 4. Start Miner Instance(s)
Based on the `threads` setting, it either creates a single `Miner` object or uses the `Forker` class to create multiple child processes, each with its own `Miner` instance.
*   **File:** [`utils/miner.php`](../utils/miner.php)
*   **Function:** `startMiner()`

## Main Mining Loop

The miner now enters the main mining loop, which repeats indefinitely until the process is terminated.

### 1. Fetch Alternative Mining Nodes
Before the loop begins, the miner makes a one-time call to the primary node to get a list of other available mining nodes. This list is stored for fallback purposes.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `getMiningNodes()`

### 2. Get Mining Info
At the start of each loop iteration, the miner requests the latest blockchain information from the node. This includes the current `height`, `difficulty`, last `block` ID, and the node's current `time`. If the primary node fails, it iterates through the list of alternative nodes until it gets a successful response.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `getMiningInfo()`

### 3. Calculate Time Offset
The miner calculates the time difference between its local system clock and the node's clock (`offset = nodeTime - now`). This offset is used to synchronize time-sensitive calculations.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

### 4. Enter Hashing Sub-Loop
The miner enters a nested `while` loop that runs until a block is found. Each iteration of this inner loop represents a single hashing attempt.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

### 5. Calculate Elapsed Time
Inside the hashing sub-loop, it calculates the `elapsed` time: `now - offset - block_date`. This represents the seconds passed since the last block was created, adjusted for the clock offset.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

### 6. Calculate Argon2id Hash
The miner computes an Argon2id hash using PHP's `password_hash()` function.
*   **Input String:** A base string formatted as `{$prev_block_date}-{$elapsed}`.
*   **Algorithm:** `HASHING_ALGO` (Argon2id).
*   **Options:** The hashing options are determined by block height. For `height >= UPDATE_3_ARGON_HARD`, the options are `['memory_cost' => 32768, "time_cost" => 2, "threads" => 1]`. For earlier blocks, `memory_cost` is `2048`.
*   **File:** [`include/class/Block.php`](../include/class/Block.php)
*   **Function:** `calculateArgonHash()` (calls `hashingOptions()`)

### 7. Calculate Nonce
A unique nonce is derived for this attempt.
*   **Input String:** A base string formatted as `{$chain_id}{$this->miner}-{$prev_block_date}-{$elapsed}-{$this->argon}`.
*   **Algorithm:** The input string is hashed once with `sha256`.
*   **File:** [`include/class/Block.php`](../include/class/Block.php)
*   **Function:** `calculateNonce()`

### 8. Calculate Hit
A numerical "hit" value is calculated.
*   **Input String:** A base string formatted as `{$this->miner}-{$this->nonce}-{$this->height}-{$this->difficulty}`.
*   **Algorithm:** The input string is double-hashed with `sha256`. The first 8 characters (4 bytes) of the final hex hash are taken. This hex value is converted to a GMP number (`$value`).
*   **Final Calculation:** `hit = ('0xffffffff' * BLOCK_TARGET_MUL) / $value`. The `BLOCK_TARGET_MUL` is a constant multiplier (`1000`) used to increase the magnitude of the final hit value, allowing for finer-grained comparisons against the target.
*   **File:** [`include/class/Block.php`](../include/class/Block.php)
*   **Function:** `calculateHit()`

### 9. Calculate Target
A dynamic "target" value is calculated for this specific attempt.
*   **Algorithm:** `target = (difficulty * BLOCK_TIME) / elapsed`. The calculation uses GMP for arbitrary-precision arithmetic.
*   **File:** [`include/class/Block.php`](../include/class/Block.php)
*   **Function:** `calculateTarget()`

### 10. Check for Solution
The miner checks if a valid block has been found by comparing the hit and target.
*   **Condition:** `($hit > 0 && $target > 0 && $hit > $target)`
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

### 11. Check for New Network Block
Periodically (every 10 seconds of `elapsed` time), the miner calls `getMiningInfo()` again. If the `block` ID returned by the node is different from the one the miner is working on, it means another miner found a block first. The hashing sub-loop is broken, and the main mining loop starts over.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

## Block Submission

### 1. Prepare Submission Data
Once a valid block is found, the miner exits the hashing sub-loop and prepares a data payload. This includes the `argon` hash, `nonce`, `height`, `difficulty`, `hit`, `target`, and other block details.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`

### 2. Submit to Primary Node
The miner sends the data payload to the primary node's `/mine.php?q=submitHash` endpoint.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `sendHash()`

### 3. Submit to Fallback Nodes
If the submission to the primary node fails (i.e., does not return `{"status":"ok"}`), the miner iterates through the list of alternative `miningNodes` fetched during initialization. It attempts to submit the same data payload to each node in the list until one of them accepts it.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()` (logic after the call to `sendHash()`)

### 4. Log Result and Repeat
The miner logs whether the block was accepted or rejected and then `sleep(3)` before starting the entire main mining loop over again for the next block.
*   **File:** [`include/class/Miner.php`](../include/class/Miner.php)
*   **Function:** `start()`
