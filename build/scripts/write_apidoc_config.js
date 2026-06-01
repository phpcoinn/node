const fs = require("fs");
const path = require("path");

const chainIdPath = path.join(__dirname, "..", "..", "chain_id");
const outputPath = path.join(__dirname, "..", "apidoc.config.generated.json");

let chainId = "00";
try {
  chainId = fs.readFileSync(chainIdPath, "utf8").trim() || "00";
} catch (e) {
  chainId = "00";
}

const urlByChainId = {
  "00": "https://main1.phpcoin.net",
  "01": "https://node1.phpcoin.net",
};

const config = {
  title: "PHPCoin Node API",
  url: "",
  sampleUrl: urlByChainId[chainId] || "http://localhost",
};

fs.writeFileSync(outputPath, JSON.stringify(config, null, 2) + "\n", "utf8");
