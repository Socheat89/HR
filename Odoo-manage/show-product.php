curl -X POST https://www.skcosmetic.com/jsonrpc \
-H "Content-Type: application/json" \
-d '{
    "jsonrpc": "2.0",
    "method": "call",
    "params": {
        "service": "common",
        "method": "authenticate",
        "args": ["skco", "sale@skcosmetic.com", "YOUR_API_KEY_HERE", []]
    },
    "id": 1
}'
