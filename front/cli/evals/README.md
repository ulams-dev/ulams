# Agent eval

Runs a real model against `ulams mcp` on a local tenant and checks the result through the API. Not part of CI
(`yarn workspace ulams eval`; costs money). The API key is read by the harness and never printed.

```bash
yarn workspace ulams build
ULAMS_EVAL_URL=http://coffee.localhost yarn workspace ulams eval
```

Settings (environment): `ULAMS_EVAL_URL` (default `http://coffee.localhost`), `ULAMS_EVAL_MODEL` (default: the
`light` profile of the API's AI config), `ANTHROPIC_API_KEY` (default: read from the API container's config with
`docker compose -f api/docker-compose.yml exec api php artisan tinker`), `ULAMS_EVAL_BUDGET_USD` (default 1),
`ULAMS_EVAL_PRICE_IN` / `ULAMS_EVAL_PRICE_OUT` (USD per million tokens, defaults 3 and 15, deliberately high).
The run stops when the budget is reached or after 30 tool calls per task. Reports go to `evals/reports/`.
