# Mining Workflows

This document outlines the complete step-by-step workflows for the different mining processes in PHPCoin.

## CLI Miner Workflow

This section describes the process for the standalone command-line miner, executed via `utils/miner.php`.

### Initialization

1.  **Parse Command-Line Arguments & Config:** The script launches and reads settings from `miner.conf` and command-line arguments, with arguments taking precedence.
    *   **File:** [`utils/miner.php`](../../utils/miner.php)

2.  **Start Miner Instance(s):** Based on the `threads` setting, it uses the `Forker` class to create one or more child processes, each with its own `Miner` instance.
    *   **File:** [`utils/miner.php`](../../utils/miner.php)

### Main Mining Loop

1.  **Fetch Alternative Mining Nodes:** Before the loop, it gets a list of other nodes for fallback purposes.
    *   **File:** [`include/class/Miner.php`](../../include/class/Miner.php)
    *   **Function:** `getMiningNodes()`

2.  **Get Mining Info:** At the start of each loop, it requests the current `height`, `difficulty`, and last `block` ID from a node.
    *   **File:** [`include/class/Miner.php`](../../include/class/Miner.php)
    *   **Function:** `getMiningInfo()`

3.  **Enter Hashing Sub-Loop:** It enters a nested loop, where each iteration is a single hashing attempt.

4.  **Calculate Elapsed Time:** It calculates the seconds passed since the last block's timestamp, adjusted for clock offset.

5.  **Calculate Argon2id Hash:** It computes an Argon2id hash using `password_hash()` with specific memory/time costs depending on block height.
    *   **File:** [`include/class/Block.php`](../../include/class/Block.php)
    *   **Function:** `calculateArgonHash()`

6.  **Calculate Nonce:** It derives a nonce from a `sha256` hash of the chain ID, miner address, previous block date, elapsed time, and the Argon2id hash.
    *   **File:** [`include/class/Block.php`](../../include/class/Block.php)
    *   **Function:** `calculateNonce()`

7.  **Calculate Hit & Target:** It calculates the numerical `hit` and the dynamic `target` for the attempt. The `hit` calculation is scaled by `BLOCK_TARGET_MUL` (a constant set to `1000`) for precision.
    *   **File:** [`include/class/Block.php`](../../include/class/Block.php)
    *   **Functions:** `calculateHit()`, `calculateTarget()`

8.  **Check for Solution:** It checks if `hit > target`.

9.  **Check for New Network Block:** Every 10 seconds of `elapsed` time, it re-checks the mining info. If the network's block ID has changed, it aborts the current attempt and starts the main loop over.

### Block Submission

1.  **Submit to Node(s):** Once a block is found, it's submitted to the primary node's API. If that fails, it attempts to submit to the fallback nodes.
    *   **File:** [`include/class/Miner.php`](../../include/class/Miner.php)
    *   **Function:** `sendHash()`

---

## Node Miner Workflow

The integrated Node Miner follows the same core hashing loop but with key operational differences.

*   **File:** [`include/class/NodeMiner.php`](../../include/class/NodeMiner.php)

### Key Differences

1.  **Configuration:** It uses the `miner_public_key` and `miner_private_key` from the node's main config file.
2.  **Mempool Integration:** It gathers pending transactions from the node's local mempool to include in the block.
3.  **Reward Generation:** It creates and signs all reward transactions (miner, generator, masternode, etc.) for the new block.
4.  **Local Block Submission:** It adds a found block directly to its own local blockchain database.
    *   **Function:** `Block->add()`
5.  **Direct Propagation:** It immediately propagates the new block to all connected peers.
    *   **Function:** `Propagate::blockToAll('current')`
6.  **Pre-Mining Checks:** It verifies the node is not syncing, has enough peers, and has a sufficient node score before starting.

---

## Node Workflow: Verifying and Processing Blocks

This section describes how a node processes, verifies, and propagates a block, whether received from a CLI miner via the API or an internal Node Miner.

### 1. Initial Receipt and Pre-Validation
*   **CLI Miner:** A block is received at the `/mine.php?q=submitHash` endpoint. The node performs initial checks: miner version, generator status, node health, peer count, and if the submitted block height is exactly one greater than the current blockchain height.
    *   **File:** [`web/mine.php`](../../web/mine.php)
*   **Node Miner:** The block is generated internally; these checks are performed before mining even begins.

### 2. Block Construction and Reward Generation
*   **CLI Miner:** The node takes the `argon`, `nonce`, and other data from the API request. It then gathers transactions from its own mempool and generates all the necessary reward transactions (miner, generator, masternode, etc.). It signs the complete block with its own generator private key.
    *   **File:** [`web/mine.php`](../../web/mine.php)
*   **Node Miner:** This is done *before* the hashing process begins.

### 3. Core Mining Validation
The node calls the `mine()` method on the newly constructed block object. This is a critical verification step that re-calculates the `nonce`, `hit`, and `target` based on the submitted data to ensure the proof-of-work is valid.
*   **File:** [`include/class/Block.php`](../../include/class/Block.php)
*   **Function:** `mine()`

### 4. Database Insertion and Transaction Processing
If the `mine()` validation succeeds, the node calls the `add()` method. This function:
1.  Performs final validation checks (e.g., block signature, version code).
2.  Starts a database transaction.
3.  Inserts the block record into the `blocks` table.
4.  Calls `parse_block()` to process and insert every transaction from the block data into the `transactions` table, updating account balances accordingly.
5.  Commits the database transaction.
*   **File:** [`include/class/Block.php`](../../include/class/Block.php)
*   **Functions:** `add()`, `parse_block()`

### 5. Propagation
Once the block is successfully added to the local database, the node immediately propagates it to its peers.
1.  The `Propagate::blockToAll()` method is called.
2.  This method executes a command-line script (`php cli/propagate.php`) in a new background process.
3.  The `propagate.php` script fetches all active peers and sends the new block to each one.
*   **File:** [`include/class/Propagate.php`](../../include/class/Propagate.php)
*   **Function:** `blockToAll()`
