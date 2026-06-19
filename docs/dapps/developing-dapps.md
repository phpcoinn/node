[PHPCoin Docs](../) > [dApps](./) > Developing Dapps


---

# Developing Dapps

A Decentralized Application (Dapp) on PHPcoin is a web application that interacts with the PHPcoin blockchain. Dapps can be built using standard web technologies like PHP, HTML, and JavaScript.

## Dapp Structure

A Dapp is typically structured as a web page that is served by a web server. The Dapp's frontend is built with HTML and JavaScript, and it interacts with a PHPcoin node's API to get information from the blockchain and to send transactions.

The backend of a Dapp can be written in any language, but PHP is a natural choice for PHPcoin Dapps. The backend can be used to perform more complex operations, such as interacting with a database or calling external APIs.

## Interacting with the Blockchain

Dapps interact with the PHPcoin blockchain by making calls to a node's API. The API provides a set of endpoints for getting information about the blockchain, sending transactions, and interacting with smart contracts.

You can use any HTTP client to make calls to the API. In PHP, you can use the `file_get_contents()` function or the cURL library. In JavaScript, you can use the `fetch()` API or a library like Axios.

## Example Dapp

The live reference demo is under the main dapps bundle:

**`dapps/PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/demo/`**

On a node: `/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/demo/`

It is an interactive **“Dapps functions”** page — an accordion UI that exercises helpers from `include/dapps.functions.php` (`dapps_get`, `dapps_post`, `dapps_api`, sessions, redirects, local exec, etc.). Use **Try it** buttons to see request/response shapes.

A companion file **`demo/api.php`** returns sample JSON for the `dapps_request()` demo.

> **Note:** An older root-level `node/dapps/demo/` (network topology / vivagraph experiment) was removed; only the PeC85 bundle demo remains.
