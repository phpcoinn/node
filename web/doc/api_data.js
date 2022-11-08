define({ "api": [
  {
    "type": "get",
    "url": "/api.php?q=base58",
    "title": "base58",
    "name": "base58",
    "group": "API",
    "description": "<p>Converts a string to base58.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Input string</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Output string</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=checkAddress",
    "title": "checkAddress",
    "name": "checkAddress",
    "group": "API",
    "description": "<p>Checks the validity of an address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "address",
            "description": "<p>Address</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>True if the address is valid, false otherwise.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=checkSignature",
    "title": "checkSignature",
    "name": "checkSignature",
    "group": "API",
    "description": "<p>Checks a signature against a public key</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "signature",
            "description": "<p>signature</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "data",
            "description": "<p>signed data</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>true or false</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=currentBlock",
    "title": "currentBlock",
    "name": "currentBlock",
    "group": "API",
    "description": "<p>Returns the current block.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Block id</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "generator",
            "description": "<p>Block Generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Height</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Block's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "nonce",
            "description": "<p>Mining nonce</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Signature signed by the generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "difficulty",
            "description": "<p>The base target / difficulty</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "transactions",
            "description": "<p>Number of transactions in block</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "version",
            "description": "<p>Block version</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "argon",
            "description": "<p>Mining argon hash</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "miner",
            "description": "<p>Miner who found block</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=generateAccount",
    "title": "generateAccount",
    "name": "generateAccount",
    "group": "API",
    "description": "<p>Generates a new account.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "address",
            "description": "<p>Account address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "private_key",
            "description": "<p>Private key</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getAddress",
    "title": "getAddress",
    "name": "getAddress",
    "group": "API",
    "description": "<p>Converts the public key to an PHP address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>The public key</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Contains the address</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getBalance",
    "title": "getBalance",
    "name": "getBalance",
    "group": "API",
    "description": "<p>Returns the balance of a specific address or public key.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "address",
            "description": "<p>Address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The PHP balance</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getBlock",
    "title": "getBlock",
    "name": "getBlock",
    "group": "API",
    "description": "<p>Returns the block.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block Height</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Block id</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "generator",
            "description": "<p>Block Generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Height</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Block's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "nonce",
            "description": "<p>Mining nonce</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Signature signed by the generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "difficulty",
            "description": "<p>The base target / difficulty</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "transactions",
            "description": "<p>Number of transactions in block</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "version",
            "description": "<p>Block version</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "argon",
            "description": "<p>Mining argon hash</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "miner",
            "description": "<p>Miner who found block</p>"
          },
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>List of transactions in block</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getBlockTransactions",
    "title": "getBlockTransactions",
    "name": "getBlockTransactions",
    "group": "API",
    "description": "<p>Returns the transactions of a specific block.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "height",
            "description": "<p>Block Height</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "block",
            "description": "<p>Block id</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmations",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "src",
            "description": "<p>Sender's address</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "type",
            "description": "<p>Transaction type</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type_label",
            "description": "<p>Transaction type label</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getMempoolBalance",
    "title": "getMempoolBalance",
    "name": "getMempoolBalance",
    "group": "API",
    "description": "<p>Returns only balance in mempool of a specific address or public key.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "address",
            "description": "<p>Address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The PHP balance in mempool</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getPeers",
    "title": "getPeers",
    "name": "getPeers",
    "group": "API",
    "description": "<p>Return all peers from node</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "id",
            "description": "<p>Id of peer in internal database (not relevant)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "hostname",
            "description": "<p>Peer hostname</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "blacklisted",
            "description": "<p>UNIX timestamp until peer is blacklisted</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "ping",
            "description": "<p>UNIX timestamp when peer was last pinged</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fails",
            "description": "<p>Number of failed conections to peer</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "stuckfail",
            "description": "<p>Number of failed stuck conentions to peer</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Blockchain height of peer</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "appshash",
            "description": "<p>Hash of peer apps</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "score",
            "description": "<p>Peer node score</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "blacklist_reason",
            "description": "<p>Reason why peer is blacklisted</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "version",
            "description": "<p>Version of peer node</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getPendingBalance",
    "title": "getPendingBalance",
    "name": "getPendingBalance",
    "group": "API",
    "description": "<p>Returns the pending balance, which includes pending transactions, of a specific address or public key.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "address",
            "description": "<p>Address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The PHP balance</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getPublicKey",
    "title": "getPublicKey",
    "name": "getPublicKey",
    "group": "API",
    "description": "<p>Returns the public key of a specific address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "address",
            "description": "<p>Address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The public key</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getTransaction",
    "title": "getTransaction",
    "name": "getTransaction",
    "group": "API",
    "description": "<p>Returns one transaction.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "transaction",
            "description": "<p>Transaction ID</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>Transaction type</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmation",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type_label",
            "description": "<p>Transaction type label</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=getTransactions",
    "title": "getTransactions",
    "name": "getTransactions",
    "group": "API",
    "description": "<p>Returns the latest transactions of an address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "address",
            "description": "<p>Address</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "limit",
            "description": "<p>Number of confirmed transactions, max 100, min 1</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "offset",
            "description": "<p>Offset for paginating transactions</p>"
          },
          {
            "group": "Parameter",
            "type": "object",
            "optional": true,
            "field": "filter",
            "description": "<p>Additional parameters to filter query</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "filter.address",
            "description": "<p>Filter transactions by address</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "filter.type",
            "description": "<p>Filter transactions by type</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "filter.dir",
            "description": "<p>Filter transactions by direction: send or receive</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmation",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "sign",
            "description": "<p>Sign of transaction related to address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "src",
            "description": "<p>Sender's address</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "type",
            "description": "<p>Transaction type</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type_label",
            "description": "<p>Transaction label</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=mempoolSize",
    "title": "mempoolSize",
    "name": "mempoolSize",
    "group": "API",
    "description": "<p>Returns the number of transactions in mempool.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data",
            "description": "<p>Number of mempool transactions</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=nodeInfo",
    "title": "nodeInfo",
    "name": "nodeInfo",
    "group": "API",
    "description": "<p>Returns details about the node.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>A collection of data about the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.hostname",
            "description": "<p>The hostname of the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.version",
            "description": "<p>The current version of the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.network",
            "description": "<p>Node network.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.dbversion",
            "description": "<p>The database schema version for the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.accounts",
            "description": "<p>The number of accounts known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.transactions",
            "description": "<p>The number of transactions known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.mempool",
            "description": "<p>The number of transactions in the mempool.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.masternodes",
            "description": "<p>The number of masternodes known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.peers",
            "description": "<p>The number of valid peers.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.height",
            "description": "<p>Current height of node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.block",
            "description": "<p>Current block id of node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.time",
            "description": "<p>Current time on node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.generator",
            "description": "<p>Node who added block to blockchain</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.miner",
            "description": "<p>Node who mined a block</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.masternode",
            "description": "<p>Masternode who received reward for block</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.totalSupply",
            "description": "<p>Total supply of coin</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.currentSupply",
            "description": "<p>Current coin value in circulation</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.avgBlockTime10",
            "description": "<p>Average block time for last 10 blocks</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.hashRate10",
            "description": "<p>Hash rate for last 10 blocks</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.hashRate100",
            "description": "<p>Hash rate for last 100 blocks</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.lastBlockTime",
            "description": "<p>Date of last block</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=send",
    "title": "send",
    "name": "send",
    "group": "API",
    "description": "<p>Sends a transaction.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value (without fees)</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Destination address</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Sender's public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "signature",
            "description": "<p>Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format. Requried when the transaction is pre-signed.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "message",
            "description": "<p>A message to be included with the transaction. Maximum 128 chars.</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "type",
            "description": "<p>The type of the transaction. 1 to send coins.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Transaction id</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=sync",
    "title": "sync",
    "name": "sync",
    "group": "API",
    "description": "<p>Returns details about the node's sync process.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>A collection of data about the sync process.</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.sync_running",
            "description": "<p>Whether the sync process is currently running.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.last_sync",
            "description": "<p>The timestamp for the last time the sync process was run.</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.sync",
            "description": "<p>Whether the sync process is currently synchronising.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": "/api.php?q=version",
    "title": "version",
    "name": "version",
    "group": "API",
    "description": "<p>Returns the node's version.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Version</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../include/class/Api.php",
    "groupTitle": "API"
  }
] });
