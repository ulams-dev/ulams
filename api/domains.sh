#!/bin/bash
# Prints, one per line, every domain that needs its own workers: the hosts listed in
# MULTI_DOMAINS plus the tenants registered in config/domain.php (written by
# `php artisan ulams:tenant:create` / `ulams:tenant:sync-env`). Only hosts with their own
# `.env.<host>` file are listed. Reads the files directly, so it is cheap to call in a loop.
cd "$(dirname "$0")" || exit 1
{
  if [ -n "$MULTI_DOMAINS" ]; then
    echo "$MULTI_DOMAINS" | tr ',' '\n'
  fi
  php -r '
    $config = @include "config/domain.php";
    foreach (array_keys(is_array($config) ? ($config["domains"] ?? []) : []) as $domain) {
      if (is_file(".env." . $domain)) {
        echo $domain, PHP_EOL;
      }
    }
  '
} | sed '/^[[:space:]]*$/d' | sort -u
