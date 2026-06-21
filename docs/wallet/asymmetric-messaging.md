[PHPCoin Docs](../) > [Wallet](./) > Asymmetric Messaging


---

# Asymmetric Messaging

PHPCoin supports **public-key message encryption** using the same **secp256k1** keys as wallet accounts. A sender encrypts plaintext for a recipient's public key; only the matching private key can decrypt it.

This is separate from wallet **password encryption** (`encryptString` / `decryptString`) and from transaction signing. It is intended for private account-to-account payloads, tooling, and future wallet-integrated messaging.

---

## Overview

| Step | Who | Action |
|------|-----|--------|
| 1 | Sender | Encrypt message with recipient **public key** |
| 2 | Transport | Share the returned **base64 packet** (off-chain today — email, chat, dapp, etc.) |
| 3 | Receiver | Decrypt with matching wallet **private key** |

**Status:** **Production node feature** — implemented in core, **deployed on mainnet** (Jun 2026). Available via API, CLI, wallet, and `phpcoin-crypto` (PHP ↔ Node ↔ browser). Cross-language interop verified. Not yet wired into on-chain transactions or the web wallet UI.

---

## Crypto model

| Layer | Choice |
|-------|--------|
| Keys | PHPCoin secp256k1 account keys (base58) |
| Key agreement | ECDH with a per-message **ephemeral sender key** (`epk`) |
| Payload cipher | AES-256-GCM |
| Key derivation | HKDF-SHA256, info string `PHPCoin asymmetric encryption v1` |
| Algorithm tag | `ECDH-secp256k1+A256GCM` |

Each message uses a fresh ephemeral keypair, so past messages are not exposed if a long-term key is later compromised (forward secrecy per message).

---

## Packet format

CLI and API transport is a **single base64 string**. Decoding it yields JSON:

```json
{
  "alg": "ECDH-secp256k1+A256GCM",
  "iv": "<base64>",
  "tag": "<base64>",
  "epk": "<recipient-readable ephemeral public key, base58>",
  "ciphertext": "<base64>"
}
```

The receiver derives the shared secret from `epk` + their private key, then decrypts with AES-GCM using the same AAD string as the sender.

---

## Encrypt (sender)

### Node API

```
GET /api.php?q=encryptForPublicKey&message=<plaintext>&public_key=<recipient_public_key>
```

**Response:** JSON wrapper with `data` set to the base64 packet string.

**Example:**

```bash
curl -s "http://127.0.0.1/api.php?q=encryptForPublicKey&message=hello&public_key=PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSC..."
```

See also [API Reference — encryptForPublicKey](../api/api-reference.md#encryptforpublickey).

### CLI util

```bash
php cli/util.php encrypt-for-public-key "hello world" <recipient_public_key>
```

Prints the base64 packet to stdout.

### JavaScript (Node / browser)

From `phpcoin-crypto` / `phpcoin-crypto.browser.js`:

```javascript
const packetB64 = encryptForPublicKey('hello world', recipientPublicKey)
// packetB64 is base64(JSON packet) — same transport format as PHP
```

---

## Decrypt (receiver)

Unlock the wallet that holds the matching private key, then:

```bash
php utils/wallet.php decrypt-message <payload_b64>
```

The wallet prints the original plaintext.

There is no dedicated decrypt API endpoint by design — decryption requires the private key and should stay wallet-local.

---

## Regression tests

Cross-platform smoke tests live in `node/dev/asym_poc/` (dev harness only — **not** the implementation; core code is under `include/`, `Api.php`, and `phpcoin-crypto`):

```bash
cd node/dev/asym_poc
./test_all.sh
```

The suite verifies PHP core, Node core, Node CLI → PHP CLI, PHP CLI → Node CLI, invalid-key rejection, and wrong-key rejection.

Thin CLIs (same core libraries as production):

```bash
node asym_cli.mjs encryptForPublicKey "<message>" <public_key>
php asym_cli.php decryptWithPrivateKey <payload_b64> <private_key>
```

---

## Implementation map

| Surface | Location |
|---------|----------|
| PHP core | `include/common.functions.php` — `generateEncryptionKeyPair`, `encryptForPublicKey`, `decryptWithPrivateKey` |
| HTTP API | `Api::encryptForPublicKey` |
| CLI util | `Util::encryptForPublicKey` → `encrypt-for-public-key` |
| Wallet CLI | `Wallet::decryptMessage` → `decrypt-message` |
| JS library | `phpcoin-crypto` + `web/apps/common/js/phpcoin-crypto.browser.js` |
| Regression tests | `node/dev/asym_poc/test_all.sh` |

---

## Limitations and next steps

- **Off-chain only** — packets are not a transaction type yet; delivery is up to the application.
- **No sender authentication** — the envelope is not signed; recipients cannot cryptographically verify who sent a message without an additional layer.
- **No key discovery** — you must already know the recipient's public key (e.g. from `getPublicKey` or wallet export).
- **Web wallet UI** — CLI and API only today; browser decrypt flow not integrated in wallet2.

Possible follow-ups: signed envelopes, on-chain or `tx_data` message carriage, wallet UI for encrypt/decrypt, address-book public key lookup.

---

## Related docs

* [Using the Wallet](./using-the-wallet.md) — wallet CLI command list
* [Features](../introduction/features.md) — ecosystem feature overview
* [API Reference](../api/api-reference.md) — `encryptForPublicKey`
