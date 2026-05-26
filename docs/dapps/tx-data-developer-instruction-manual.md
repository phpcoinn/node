[PHPCoin Docs](../) > [dApps](./) > TX_DATA Developer Instruction Manual


---

# PHPCoin TX_DATA Developer Instruction Manual

## Overview

PHPCoin TX_DATA introduces a lightweight decentralized application architecture based on structured indexed blockchain data instead of traditional immutable smart contracts.

The blockchain stores:
- immutable structured records
- indexed application data
- decentralized ownership
- ordered event history

Applications and services:
- read blockchain data
- interpret records
- execute business logic
- expose APIs
- provide user interfaces

This architecture avoids:
- virtual machines
- gas metering complexity
- immutable contract logic
- arbitrary on-chain code execution

---

# Core Concept

Instead of deploying smart contracts, developers create:

```text
TX_DATA transactions
```

containing structured indexed fields.

Applications define:
- namespaces
- actions
- field meanings
- interpretation logic

Services monitor blockchain data and react accordingly.

---

# TX_DATA Transaction Structure

Every TX_DATA transaction contains:

| Field | Description |
|---|---|
| app | application namespace |
| action | application action |
| indexed fields | searchable blockchain data |
| optional JSON | extra non-indexed metadata |

---

# Example TX_DATA

## Example: Blockchain Cron

```json
{
  "type": "TX_DATA",
  "app": "cron",
  "action": "create",

  "string1": "https://app.dap.ad/task",
  "int1": 30,
  "int2": 500000
}
```

Meaning:

| Field | Meaning |
|---|---|
| string1 | target URL |
| int1 | interval in blocks |
| int2 | start block |

---

# tx_data Storage Layer

Recommended table structure:

```sql
CREATE TABLE tx_data (

    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tx_id BIGINT NOT NULL,

    app VARCHAR(64) NOT NULL,
    action VARCHAR(64) NOT NULL,

    string1 VARCHAR(255),
    string2 VARCHAR(255),
    string3 VARCHAR(255),

    int1 BIGINT,
    int2 BIGINT,
    int3 BIGINT,

    float1 DOUBLE,
    float2 DOUBLE,

    address1 VARCHAR(128),
    address2 VARCHAR(128),

    json_data LONGTEXT,

    created_block BIGINT,
    created_at INT
);
```

---

# Indexed Fields

Indexed fields are optimized for searching.

Recommended indexed columns:

```sql
CREATE INDEX idx_app ON tx_data(app);
CREATE INDEX idx_action ON tx_data(action);
CREATE INDEX idx_app_action ON tx_data(app, action);

CREATE INDEX idx_int1 ON tx_data(int1);
CREATE INDEX idx_int2 ON tx_data(int2);

CREATE INDEX idx_address1 ON tx_data(address1);
```

---

# Application Namespaces

Applications should use namespaces to avoid collisions.

Examples:

```text
cron
market
social
dns
ads
staking
```

---

# Blockchain-Native Cron

PHPCoin scheduling can use blockchain height instead of wall-clock time.

Example:

```text
Every 30 blocks
```

instead of:

```text
Every 5 minutes
```

---

# Cron Scheduling Logic

Example execution logic:

```php
if(($currentBlock - $startBlock) % $interval == 0) {
    execute();
}
```

This removes the need for mutable execution state.

---

# Cron Service Example

Example cron query:

```sql
SELECT * FROM tx_data
WHERE app='cron'
AND action='create'
AND MOD(current_block - int2, int1) = 0
```

---

# Service Architecture

Applications are implemented as external services.

Example services:

- cron executors
- marketplace APIs
- DNS resolvers
- social feeds
- ad engines
- staking processors

Services are:
- replaceable
- upgradeable
- independently scalable

---

# Example Marketplace Record

```json
{
  "app": "market",
  "action": "list",

  "string1": "item_100",
  "int1": 500,
  "string2": "PHPC"
}
```

Meaning:

| Field | Meaning |
|---|---|
| string1 | item id |
| int1 | price |
| string2 | currency |

---

# Example Social Post

```json
{
  "app": "social",
  "action": "post",

  "string1": "Hello PHPCoin!"
}
```

---

# API Query Example

```http
GET /api/tx-data/search?app=social&action=post
```

---

# PHP Query Example

```php
$stmt = $db->prepare("
    SELECT *
    FROM tx_data
    WHERE app = :app
    AND action = :action
");

$stmt->execute([
    ':app' => 'social',
    ':action' => 'post'
]);

$rows = $stmt->fetchAll();
```

---

# Explorer Integration

Explorers should:
- detect TX_DATA transactions
- display app/action
- render indexed fields
- support app-specific formatting

Example:
- social posts rendered as posts
- cron tasks rendered as schedulers
- marketplace listings rendered as listings

---

# Wallet Integration

Wallets should support:
- creating TX_DATA transactions
- editing indexed fields
- app selection
- JSON metadata editing

Future enhancements may include:
- schema-based forms
- auto-generated UI
- app templates

---

# Transaction Fees

TX_DATA transactions pay normal blockchain fees.

Fees compensate for:
- permanent storage
- indexing
- replication
- bandwidth

Recommended fee factors:
- number of indexed fields
- JSON size
- transaction size

---

# Security Philosophy

The blockchain validates:
- signatures
- balances
- field limits
- transaction structure

The blockchain does NOT execute:
- arbitrary application code
- smart contracts
- dynamic VM logic

This greatly reduces:
- attack surface
- consensus complexity
- chain bloat

---

# Recommended Limits

| Resource | Suggested Limit |
|---|---|
| string field | 255 bytes |
| JSON blob | 64KB |
| indexed fields | fixed count |
| tx size | protocol limit |

---

# Cron Security Recommendations

Recommended restrictions:

- HTTPS only
- block private IP ranges
- block localhost
- strict timeouts
- redirect limits
- response size limits

---

# AI-Friendly Architecture

TX_DATA architecture aligns naturally with AI-assisted development.

AI tools can easily generate:
- APIs
- services
- dashboards
- blockchain parsers
- event processors

Developers can use:
- PHP
- Python
- Node.js
- Go
- Rust

without learning:
- Solidity
- WASM VMs
- gas optimization

---

# Architectural Philosophy

Traditional blockchains:
> blockchain as computer

PHPCoin TX_DATA:
> blockchain as decentralized indexed data layer

This architecture focuses on:
- structured truth
- event coordination
- decentralized persistence
- application interoperability

instead of arbitrary on-chain computation.

---

# Suggested Initial Applications

Recommended first applications:

1. Blockchain-native cron
2. dap.ad DNS records
3. Marketplace listings
4. Social posts
5. Ad campaigns
6. Automation systems

---

# Future Possibilities

Possible future extensions:

- schema registry
- auto-generated wallet forms
- app discovery registry
- decentralized indexing services
- event subscriptions
- typed field validation

---

# Conclusion

PHPCoin TX_DATA provides a lightweight alternative to smart contract blockchains.

Instead of immutable executable code, PHPCoin uses:
- structured indexed blockchain records
- decentralized event storage
- off-chain upgradeable services

This creates a simpler, safer and more scalable foundation for decentralized internet infrastructure and AI-assisted application development.
