# PHPCoin node (`node/`) — Project Status

**Project:** `phpcoin/phpcoin` — core blockchain node  
**Reviewed on:** 2026-06-17  
**Git:** `https://github.com/phpcoinn/node` — local HEAD `250274a` (mainnet **1.10.3.404**)  
**Status:** **KEEP — production core.** Always critical path for mainnet/testnet.

> **Review scope:** **Documentation and catalog only** — same as `node/dev/` and `node/dapps/` reviews. **No execution** (no refactors, credential scrub, or deploy changes in this pass).  
> **Out of scope here (separate docs):** [dapps/PROJECT_STATUS.md](dapps/PROJECT_STATUS.md), [dev/PROJECT_STATUS.md](dev/PROJECT_STATUS.md).

---

## What this repo is

Single PHP codebase for a **full PHPCoin node**:

| Concern | Implementation |
|---------|----------------|
| **Consensus** | ePoW (Argon2 elapsed PoW) — `Miner.php`, `NodeMiner.php` |
| **Ledger** | MySQL/MariaDB — `Block.php`, `Transaction.php`, `Account.php`, `Blockchain.php` |
| **P2P (today)** | HTTP — `Peer.php`, `PeerPost.php`, `cli/propagate.php`, `NodeSync.php` |
| **Mempool / sync** | `Mempool.php`, `Sync.php`, `Forker.php` |
| **Smart contracts** | `SmartContract.php`, `SmartContractEngine.php`, `include/class/sc/` sandbox |
| **TX_DATA** | `Transaction.php` — indexed `tx_data` payloads (type + canonical JSON) |
| **Dapp hosting** | `Dapps.php` + `web/dapps.php` (bundle gitignored under `dapps/`) |
| **Public API** | `web/api.php` → `Api.php` (~80+ methods) |
| **Ops** | `cli/cron.php` → `Cron.php` / `Task.php` (sync, masternode, dapps, miner) |
| **Schema** | `include/schema/*.sql` + `utils/db_updater.phar` on startup ([dev/db_updater](dev/db_updater/README.md)) |

**Networks:** `chain_id` file (`00` mainnet / `01` testnet) selects `coinspec.inc.php` vs `coinspec.01.inc.php` + schema variant.

---

## Architecture (runtime)

```
                    ┌─────────────────────────────────────────┐
                    │  nginx / PHP-FPM                        │
                    └─────────────────────────────────────────┘
         │                    │                    │
         ▼                    ▼                    ▼
   web/api.php          web/dapps.php       web/apps/* (explorer, docs, …)
         │                    │                    │
         └────────────────────┼────────────────────┘
                              ▼
                    include/init.inc.php
                    (schema check, config, maintenance)
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
    Block / Tx /         SmartContractEngine    Dapps / Masternode
    Peer / Mempool       + sc sandbox           NodeMiner / Sync
         │                    │                    │
         └────────────────────┼────────────────────┘
                              ▼
                         MySQL (phpcoin)

   Background (cron every ~60s):
   cli/cron.php → Sync | Masternode | Dapps | NodeMiner | housekeeping util.php

   Planned (decision 2026-06-08): separate peer daemon — WebSocket + Amp
   ([dev/amphp/PROJECT_STATUS.md](dev/amphp/PROJECT_STATUS.md)); API stays HTTP.
```

---

## Directory map (core — not `dapps/` or `dev/`)

| Path | Role | Verdict |
|------|------|---------|
| **`include/`** | Bootstrap, coinspec, genesis, rewards, checkpoints, **`class/`** (~21k LOC) | **Core — maintain** |
| **`include/schema/`** | Canonical DDL: `mainnet.sql`, `testnet.sql`, pruned variants; `DB_SCHEMA_VERSION` in coinspect | **Core** |
| **`include/class/sc/`** | SC compiler, sandbox, PHAR builder, templates, disable list | **Core SC** |
| **`cli/`** | `cron.php`, `util.php` (50+ admin cmds), `propagate.php`, `sync.php`, `miner.php`, `dapps.php`, peer tools | **Ops — maintain** |
| **`web/`** | HTTP entrypoints | **Production surface** |
| **`web/api.php`** | JSON API for wallets, bots, explorers | **Critical** |
| **Asymmetric messaging** | `Api::encryptForPublicKey`, `cli/util.php encrypt-for-public-key`, `Wallet::decryptMessage` | **Complete** |
| **`web/dapps.php`** | On-chain dapp router | **Critical** (bundle in `dapps/`) |
| **`web/mine.php`** | Classic Argon2 mining API (browser/cppminer) | **Production** |
| **`web/apps/explorer/`** | Block explorer UI + tx_data views | **Production** |
| **`web/apps/docs/`** | Renders `node/docs/*.md` (Parsedown) | **Production docs UI** |
| **`web/apps/common/`** | Minia theme + shared JS (`phpcoin-crypto`, web-miner) | **Production shell** |
| **`web/apps/admin/`** | Node admin (gated by `$_config['admin']`) | **Ops** |
| **`web/apps/wallet/`**, **`wallet2/`** | Legacy in-node wallets | **Legacy** — superseded by dapps `wallet/` + [web-wallet](../web-wallet/docs/PROJECT_STATUS.md) |
| **`web/apps/wallet3/`** | Symlink → `web-wallet/dist` | **Dev/deploy convenience** — not primary dapps path |
| **`web/apps/miner/`**, **`faucet/`** | Thin wrappers / old UIs | **Legacy** — dapps `miner/` is hub |
| **`web/apps/trader/`** | Disabled DEX UI (`die()` in places) | **Legacy / do not extend** |
| **`utils/`** | `db_updater.phar`, `miner.php`, `wallet.php`, `sdk.php`, `scutil.php`, `sc_compile.php`, `vanitygen.php` | **Shipped utilities** |
| **`scripts/`** | `install_node.sh`, `install_update.sh`, `install_node_mainnet.sh` | **Canonical install** (web-install must match) |
| **`config/`** | `config.default.php`, sample; **`config.inc.php` gitignored** | **Per-node secrets** |
| **`build/`** | PHAR builders, apidoc, bundled JS, docker snippets | **Build tooling** |
| **`dist/`** | `phpcoin-wallet`, `phpcoin-miner` PHARs (gitignored) | **Release artifacts** |
| **`docs/`** | Primary Markdown documentation (29 pages) | **Maintain** — wiki migration pending |
| **`vendor/`** | Minimal Composer (mostly autoload stub) | **Small** |
| **`tmp/`** | Logs, locks, SC run dirs, propagate state | **Runtime — gitignored** |

**Not core (gitignored or separate):**

| Path | Notes |
|------|-------|
| **`dapps/`** | Production PeC85 bundle — [dapps/PROJECT_STATUS.md](dapps/PROJECT_STATUS.md) |
| **`dev/`** | Workshop — [dev/PROJECT_STATUS.md](dev/PROJECT_STATUS.md) |
| **`node_modules/`** | ~665 MB — Solana/wallet experiments at repo root; **consider gitignore** |
| **`blockchain.sql.zip`**, **`db_pruned.sql*`** | ~1.1 GB + 296 MB install artifacts in tree — **should not be in git long-term** |

---

## Core classes (`include/class/`)

| Class | Responsibility |
|-------|------------------|
| `Block`, `Blockchain`, `Transaction`, `Account` | Ledger, validation, **TX_DATA** indexing |
| `Mempool` | Pending txs |
| `Peer`, `PeerPost`, `PeerRequest` | Peer DB + HTTP calls |
| `Sync`, `NodeSync` | Chain sync task |
| `Propagate`, `Forker` | Block/tx fan-out (HTTP + fork workers) |
| `Miner`, `NodeMiner` | ePoW mining + node miner task |
| `Masternode` | MN collateral, rewards, task |
| `SmartContract`, `SmartContractEngine` | SC deploy, invoke, state |
| `Dapps` | Bundle verify, propagate, render |
| `Api` | Public HTTP API surface |
| `Util`, `Nodeutil` | CLI helpers, DB schema, node info, asymmetric packet sender |
| `Wallet` | Wallet CLI, including asymmetric packet decrypt |
| `Cron`, `Task` | Scheduled task framework |
| `Config`, `Cache`, `Wallet`, `SdkUtil` | Config DB, caching, wallet PHAR entry, SDK helpers |
| `Pajax` | Legacy AJAX helper |

**SC subtree:** `sc/Sandbox.php`, `Compiler.php`, `PharBuilder.php`, `StatePersistence.php`, sandbox INI + whitelist generator.

---

## CLI & background tasks

| Entry | Purpose |
|-------|---------|
| **`cli/cron.php`** | Main loop — enables `Sync`, `Masternode`, `Dapps`, `NodeMiner` via config flags |
| **`cli/util.php`** | Operator Swiss army knife (`peers`, `propagate`, `exportdb`, `db-schema`, `update`, …) |
| **`cli/propagate.php`** | Block/tx/dapp broadcast to peers |
| **`cli/sync.php`**, **`peersync.php`**, **`dbsync.php`** | Sync variants |
| **`cli/miner.php`**, **`masternode.php`**, **`dapps.php`** | Task-specific CLIs |

**Task locks:** `tmp/cli-{name}.lock` — one instance per task name.

---

## Web API & apps

**`web/api.php`:** `?q=` dispatches to `Api::*`; CORS open; optional `public_api` / `allowed_hosts` gate.

**Notable bundled apps (under `/apps/`):**

| App | Status |
|-----|--------|
| **explorer** | Primary chain explorer — rewards, SC, tokens, **tx_data** |
| **docs** | In-node doc browser → `node/docs/` |
| **common** | Shared Minia assets — source of truth for dapps chrome |
| **admin** | Wallet generation, task toggles, server tabs |
| **wallet / wallet2** | Legacy — keep until fully retired |
| **wallet3** | Symlink to external web-wallet build |

**Dapps:** served from **`/dapps.php?url=PeC85…/…`** — see dapps doc. **`web-wallet`** deploys into `dapps/…/wallet/`.

**Experiments / stale web files:**

| File | Notes |
|------|-------|
| `wsserver.php`, `cli/server.php` | OpenSwoole WebSocket stubs — **`PeerWebSocketServer` class missing** in repo; incomplete precursor to [amphp](dev/amphp/PROJECT_STATUS.md) work |
| `web/async.php` | SSE demo (CoinGecko) — not production |
| `web/dev.php` | Dev helper |
| `web/install.php` | Legacy installer entry (scripts/ is canonical) |

---

## Build & release

| Artifact | Builder | Notes |
|----------|---------|-------|
| `dist/phpcoin-wallet` | `composer build-wallet` → `build/make_wallet.php` | CLI wallet PHAR |
| `dist/phpcoin-miner` | `composer build-miner` → `build/make_miner.php` | Official PHP miner |
| `utils/db_updater.phar` | [dev/db_updater](dev/db_updater/) | Shipped in repo; auto schema on init |
| `build/js/phpcoin-crypto*.js` | Manual / copy from phpcoin-crypto | Used by common + miners |

**Version (mainnet coinspect):** `1.10.3` build **404**, `DB_SCHEMA_VERSION` **107**, min peer **1.10.2**.

---

## Configuration & networks

| Mechanism | Detail |
|-----------|--------|
| **`chain_id`** | File at repo root — `00` / `01` |
| **`config/config.inc.php`** | Local DB, hostname, feature flags (gitignored) |
| **`config/dapps.config.inc.php`** | Dapp secrets (gitignored) |
| **`coinspec.inc.php` / `.01.inc.php`** | Network constants, `MAIN_DAPPS_ID`, rewards |
| **`checkpoints.php` / `.01.php`** | Hard-coded block hashes |

**Feature flags (DB config):** `sync`, `masternode`, `dapps`, `node_miner`, `cron`, `admin`, `public_api`, pruned mode, etc.

---

## Documentation (`node/docs/`)

**Primary source** for operators and developers — rendered at `/apps/docs/` on live nodes.

| Section | Topics |
|---------|--------|
| introduction, white-paper | Overview |
| getting-started | Install, pruned node |
| mining, epow, staking, masternodes | Node operations |
| smart-contracts, dapps | SC + **tx_data manual** |
| api | API reference |

**Pending:** migrate still-valid content from **GitHub node wiki** → `node/docs/`, then retire wiki ([_master-docs/PROJECT-STATUS.md](../_master-docs/PROJECT-STATUS.md)).

---

## Integrations (external to this repo)

| Consumer | Uses |
|----------|------|
| [web-wallet](../web-wallet/docs/PROJECT_STATUS.md) | `api.php`, dapps deploy, `mine.php` (stake-mine on site) |
| [telegrambot](../telegrambot/PROJECT_STATUS.md), [discordbot](../discordbot/PROJECT_STATUS.md) | `api.php`, `site/mine.php` |
| [trade](../trade/PROJECT_STATUS.md) | Node MySQL `p2p_*` tables (`schema1.sql` in repo root — trade-specific) |
| [site](../site/PROJECT_STATUS.md) | Downloads, install scripts, lightweight mine API |
| [cppminer](../cppminer/PROJECT_STATUS.md) | `web/mine.php` protocol |
| Bots / explorers / bridge | Public API |

---

## Security & hygiene

| Risk | Detail |
|------|--------|
| **`config.inc.php` / dapps config** | DB + hostname + admin — never commit |
| **`public_api`** | Default **true** in sample — lock down on private nodes |
| **Legacy wallets in tree** | wallet2 holds old patterns — don’t expose without review |
| **Large binaries in workspace** | `blockchain.sql.zip`, pruned SQL — keep out of git |
| **`node_modules/`** | 665 MB — unrelated deps (Solana); clutter + audit noise |
| **`tmp/`** | Logs may contain sensitive URLs/tokens (e.g. xapp OAuth — fix in dapp) |
| **Open WS experiments** | Do not deploy `wsserver.php` / broken `cli/server.php` as-is |

---

## Legacy / deprecation summary

| Item | Recommendation |
|------|----------------|
| `web/apps/wallet`, `wallet2` | **Freeze** — primary wallet is external SPA |
| `web/apps/trader` | **Disabled** — use [trade](../trade/) or dapps trader removal |
| `web/install.php` | Prefer **`scripts/install_node.sh`** + future [web-install](dev/web-install/) |
| HTTP-only peer layer | **Maintain** until Amp peer daemon ships |
| `package.json` root deps | Solana/bitcore — **not core node**; isolate or remove |

---

## Planned architecture (documented, not built)

- **Peer daemon:** WebSocket + Amp async — **API unchanged** — [_master-docs/DECISIONS.md](../_master-docs/DECISIONS.md), [dev/amphp/PROJECT_STATUS.md](dev/amphp/PROJECT_STATUS.md)
- **Composer libs:** Extract SDK, db_updater, etc. — [ECOSYSTEM-MAP §1.3](../_master-docs/ECOSYSTEM-MAP.md)
- **web-install:** New install UX — must match `scripts/`

---

## Verdict

| | |
|--|--|
| **Role** | **Production blockchain node** — heart of PHPCoin infrastructure |
| **Verdict** | **KEEP and maintain** — highest priority repo |
| **Review outcome** | Catalog complete; no code changes in this pass |
| **Next execution (when ready)** | Repo hygiene, peer daemon spike, wiki migration, retire legacy `/apps/wallet*` |

---

## Future tasks (not started)

1. [ ] **Git hygiene** — gitignore `node_modules/`, `blockchain.sql.zip`, `db_pruned.sql*`; confirm not pushed
2. [ ] **Wiki → `node/docs/`** migration + link audit
3. [ ] **Peer daemon** — prototype from `dev/amphp/test_future.php` + fix/remove broken `cli/server.php`
4. [ ] **Legacy wallet apps** — document retirement path for `/apps/wallet` + `wallet2`
5. [ ] **Root `package.json`** — remove or move Solana deps to `dev/` / dapps only
6. [ ] **Dapps follow-ups** — `common/`, `connect/` review; `swap/` cleanup — [dapps/PROJECT_STATUS.md](dapps/PROJECT_STATUS.md)
7. [ ] **web-install** validation vs `scripts/` before public launch
8. [ ] **Minia v2.4** migration into `web/apps/common/` — [Minia_HTML_v2.4.0](../Minia_HTML_v2.4.0/PROJECT_STATUS.md)

---

## Backend architecture (deep dive)

Cron → sync/miner tasks → HTTP peer protocol — full flow, lifecycles, and key files:

**[BACKEND-ARCHITECTURE.md](BACKEND-ARCHITECTURE.md)** (internal — not public docs)

---

## Related

- Dapps bundle: [dapps/PROJECT_STATUS.md](dapps/PROJECT_STATUS.md)
- Dev workshop: [dev/PROJECT_STATUS.md](dev/PROJECT_STATUS.md)
- Ecosystem index: [_master-docs/PROJECT-STATUS.md](../_master-docs/PROJECT-STATUS.md)
- Backend flow: [BACKEND-ARCHITECTURE.md](BACKEND-ARCHITECTURE.md) (internal)
- API: [docs/api/api-reference.md](docs/api/api-reference.md)
- Install: [docs/getting-started/quick-installation.md](docs/getting-started/quick-installation.md)

Update when major version, schema version, or deploy architecture changes.
