[PHPCoin](../../README.md) > [Docs](../README.md) > [API](README.md) > API Reference

---

# API Reference

The PHPcoin API provides a set of endpoints for interacting with the blockchain. The API is accessible via HTTP and returns data in JSON format.

## Accounts

### getAddress

Converts a public key to a PHPcoin address.

*   **URL:** `/api.php?q=getAddress`
*   **Method:** `GET`
*   **Parameters:**
    *   `public_key` (string, required): The public key to convert.
*   **Example:**
    ```
    /api.php?q=getAddress&public_key=...
    ```

### getBalance

Returns the balance of a specific address.

*   **URL:** `/api.php?q=getBalance`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address to check.
*   **Example:**
    ```
    /api.php?q=getBalance&address=...
    ```

### getPendingBalance

Returns the pending balance of a specific address, which includes unconfirmed transactions.

*   **URL:** `/api.php?q=getPendingBalance`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address to check.
*   **Example:**
    ```
    /api.php?q=getPendingBalance&address=...
    ```

### getPublicKey

Returns the public key of a specific address.

*   **URL:** `/api.php?q=getPublicKey`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address to check.
*   **Example:**
    ```
    /api.php?q=getPublicKey&address=...
    ```

### generateAccount

Generates a new PHPcoin account.

*   **URL:** `/api.php?q=generateAccount`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=generateAccount
    ```

## Transactions

### getTransactions

Returns the latest transactions for a specific address.

*   **URL:** `/api.php?q=getTransactions`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address to check.
    *   `limit` (integer, optional): The maximum number of transactions to return.
    *   `offset` (integer, optional): The offset to start from.
*   **Example:**
    ```
    /api.php?q=getTransactions&address=...&limit=10
    ```

### getTransaction

Returns a specific transaction by its ID.

*   **URL:** `/api.php?q=getTransaction`
*   **Method:** `GET`
*   **Parameters:**
    *   `transaction` (string, required): The ID of the transaction.
*   **Example:**
    ```
    /api.php?q=getTransaction&transaction=...
    ```

### send

Sends a transaction to the network.

*   **URL:** `/api.php?q=send`
*   **Method:** `POST`
*   **Parameters:**
    *   `val` (float, required): The amount to send.
    *   `dst` (string, required): The destination address.
    *   `public_key` (string, required): The sender's public key.
    *   `signature` (string, required): The transaction signature.
    *   `date` (integer, required): The transaction date (Unix timestamp).
    *   `message` (string, optional): A message to include with the transaction.
*   **Example:**
    ```json
    {
      "val": 10.0,
      "dst": "...",
      "public_key": "...",
      "signature": "...",
      "date": 1678886400,
      "message": "Hello, world!"
    }
    ```

## Blocks

### currentBlock

Returns the current block.

*   **URL:** `/api.php?q=currentBlock`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=currentBlock
    ```

### getBlock

Returns a specific block by its height.

*   **URL:** `/api.php?q=getBlock`
*   **Method:** `GET`
*   **Parameters:**
    *   `height` (integer, required): The height of the block.
*   **Example:**
    ```
    /api.php?q=getBlock&height=12345
    ```

### getBlockTransactions

Returns the transactions of a specific block.

*   **URL:** `/api.php?q=getBlockTransactions`
*   **Method:** `GET`
*   **Parameters:**
    *   `height` (integer, required): The height of the block.
*   **Example:**
    ```
    /api.php?q=getBlockTransactions&height=12345
    ```

## Node

### version

Returns the version of the node.

*   **URL:** `/api.php?q=version`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=version
    ```

### mempoolSize

Returns the number of transactions in the mempool.

*   **URL:** `/api.php?q=mempoolSize`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=mempoolSize
    ```

### nodeInfo

Returns information about the node.

*   **URL:** `/api.php?q=nodeInfo`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=nodeInfo
    ```

### getPeers

Returns a list of the node's peers.

*   **URL:** `/api.php?q=getPeers`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=getPeers
    ```

## Masternodes

### getMasternodes

Returns a list of all masternodes.

*   **URL:** `/api.php?q=getMasternodes`
*   **Method:** `GET`
*   **Example:**
    ```
    /api.php?q=getMasternodes
    ```

### getMasternode

Returns a specific masternode by its address.

*   **URL:** `/api.php?q=getMasternode`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address of the masternode.
*   **Example:**
    ```
    /api.php?q=getMasternode&address=...
    ```

## Smart Contracts

### getSmartContract

Returns a specific smart contract by its address.

*   **URL:** `/api.php?q=getSmartContract`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address of the smart contract.
*   **Example:**
    ```
    /api.php?q=getSmartContract&address=...
    ```

### getSmartContractProperty

Reads a property of a smart contract.

*   **URL:** `/api.php?q=getSmartContractProperty`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address of the smart contract.
    *   `property` (string, required): The name of the property to read.
    *   `key` (string, optional): The key of the property, if it's a map.
*   **Example:**
    ```
    /api.php?q=getSmartContractProperty&address=...&property=myProperty&key=myKey
    ```

### getSmartContractInterface

Returns the interface of a smart contract.

*   **URL:** `/api.php?q=getSmartContractInterface`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address of the smart contract.
*   **Example:**
    ```
    /api.php?q=getSmartContractInterface&address=...
    ```

### getSmartContractView

Executes a view method of a smart contract.

*   **URL:** `/api.php?q=getSmartContractView`
*   **Method:** `GET`
*   **Parameters:**
    *   `address` (string, required): The address of the smart contract.
    *   `method` (string, required): The name of the view method to execute.
    *   `params` (string, optional): The parameters for the method, as a base64-encoded JSON string.
*   **Example:**
    ```
    /api.php?q=getSmartContractView&address=...&method=myMethod&params=...
    ```
