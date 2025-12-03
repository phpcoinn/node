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

The `dapps/demo` directory contains a simple example Dapp that visualizes the PHPcoin network topology. This Dapp is a good starting point for learning how to develop Dapps on PHPcoin.

The Dapp's frontend is built with HTML and JavaScript, and it uses the `vivagraph.js` and `go.js` libraries to visualize the network graph. The Dapp's backend is written in PHP, and it uses the `file_get_contents()` function to call the node's API and get information about the network.
