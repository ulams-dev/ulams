#!/usr/bin/env python3
"""Cloudflare side of the production host names (zone ulams.app). Idempotent; prints what it changes.

  CLOUDFLARE_API_TOKEN=... CLOUDFLARE_ACCOUNT_ID=... python3 production-hosts.py <step> [--dry-run]

Steps (run in this order, see STAGING-NOTES.md "Production names"):
  dns        proxied A records of the MyDevil hosts (api, admin, h5p, pdf, <slug>, <slug>-api, <slug>-admin) and www
  pages      the apex ulams.app as a custom domain of the Pages project ulams-landing, with its CNAME
  workers    custom domains content/storage/<slug>-content on the Workers, /h5p* routes of every API host
  rules      noindex on every host except the landing; X-Ulams-Content-Origin stripped on the new API hosts too
  redirects  the old -staging hosts redirect to the new ones (301 for pages, 308 for everything that takes requests)

The staging records, Worker domains and rules are never deleted here.
"""
import json, os, ssl, sys, urllib.error, urllib.request

ZONE_NAME = "ulams.app"
ORIGIN_IP = os.environ.get("ULAMS_ORIGIN_IP", "77.79.248.122")
SLUGS = os.environ.get("ULAMS_SLUGS", "coffee oncall nightsky gravity poland ulam microdosing stablecoins").split()
PAGES_PROJECT = "ulams-landing"
TOKEN = os.environ["CLOUDFLARE_API_TOKEN"]
ACCOUNT = os.environ["CLOUDFLARE_ACCOUNT_ID"]
DRY = "--dry-run" in sys.argv
CTX = ssl.create_default_context(cafile=next((p for p in ("/etc/ssl/cert.pem", "/etc/ssl/certs/ca-certificates.crt") if os.path.exists(p)), None))


def call(method, path, body=None):
    req = urllib.request.Request("https://api.cloudflare.com/client/v4" + path, method=method, data=None if body is None else json.dumps(body).encode(),
                                 headers={"Authorization": "Bearer " + TOKEN, "Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=60, context=CTX) as r:
            return json.load(r)
    except urllib.error.HTTPError as e:
        try:
            return json.load(e)
        except Exception:
            return {"success": False, "errors": [str(e)]}


def must(d, what):
    if not d.get("success"):
        sys.exit(f"{what} failed: {d.get('errors')}")
    return d["result"]


def paged(path):
    out, page = [], 1
    while True:
        d = call("GET", f"{path}{'&' if '?' in path else '?'}per_page=100&page={page}")
        out += must(d, path)
        if page >= d.get("result_info", {}).get("total_pages", 1):
            return out
        page += 1


ZONE = must(call("GET", f"/zones?name={ZONE_NAME}"), "zone lookup")[0]["id"]
PLATFORM_HOSTS = [f"api.{ZONE_NAME}", f"admin.{ZONE_NAME}", f"h5p.{ZONE_NAME}", f"pdf.{ZONE_NAME}"]
TENANT_HOSTS = [h for s in SLUGS for h in (f"{s}.{ZONE_NAME}", f"{s}-api.{ZONE_NAME}", f"{s}-admin.{ZONE_NAME}")]
API_HOSTS = [f"api.{ZONE_NAME}"] + [f"{s}-api.{ZONE_NAME}" for s in SLUGS]
CONTENT_HOSTS = [f"content.{ZONE_NAME}"] + [f"{s}-content.{ZONE_NAME}" for s in SLUGS]


def act(msg, fn):
    print(("would " if DRY else "") + msg)
    if not DRY:
        return fn()


def step_dns():
    have = {(r["type"], r["name"]): r for r in paged(f"/zones/{ZONE}/dns_records")}
    for host in PLATFORM_HOSTS + TENANT_HOSTS:
        if ("A", host) in have or ("CNAME", host) in have:
            print(f"ok   {host}")
            continue
        act(f"add  A {host} -> {ORIGIN_IP} (proxied)", lambda h=host: must(call("POST", f"/zones/{ZONE}/dns_records", {"type": "A", "name": h, "content": ORIGIN_IP, "proxied": True}), h))
    www = f"www.{ZONE_NAME}"
    if ("A", www) not in have and ("CNAME", www) not in have:
        # a proxied placeholder: the redirect rule answers before any origin is asked
        act(f"add  A {www} -> 192.0.2.1 (proxied, redirect only)", lambda: must(call("POST", f"/zones/{ZONE}/dns_records", {"type": "A", "name": www, "content": "192.0.2.1", "proxied": True}), www))


def step_pages():
    domains = must(call("GET", f"/accounts/{ACCOUNT}/pages/projects/{PAGES_PROJECT}/domains"), "pages domains")
    if not any(d["name"] == ZONE_NAME for d in domains):
        act(f"add  Pages domain {ZONE_NAME}", lambda: must(call("POST", f"/accounts/{ACCOUNT}/pages/projects/{PAGES_PROJECT}/domains", {"name": ZONE_NAME}), "pages domain"))
    have = {(r["type"], r["name"]): r for r in paged(f"/zones/{ZONE}/dns_records")}
    if ("CNAME", ZONE_NAME) not in have:
        act(f"add  CNAME {ZONE_NAME} -> {PAGES_PROJECT}.pages.dev (proxied)", lambda: must(call("POST", f"/zones/{ZONE}/dns_records", {"type": "CNAME", "name": ZONE_NAME, "content": f"{PAGES_PROJECT}.pages.dev", "proxied": True}), "apex"))
    else:
        print(f"ok   CNAME {ZONE_NAME}")


def step_workers():
    domains = {d["hostname"]: d for d in must(call("GET", f"/accounts/{ACCOUNT}/workers/domains"), "worker domains")}
    wanted = [(h, "ulams-content") for h in CONTENT_HOSTS] + [(f"storage.{ZONE_NAME}", "ulams-storage")]
    for host, service in wanted:
        if domains.get(host, {}).get("service") == service:
            print(f"ok   {host} -> {service}")
            continue
        act(f"add  Worker domain {host} -> {service}", lambda h=host, s=service: must(call("PUT", f"/accounts/{ACCOUNT}/workers/domains", {"hostname": h, "service": s, "zone_id": ZONE, "environment": "production"}), h))
    routes = {r["pattern"] for r in must(call("GET", f"/zones/{ZONE}/workers/routes"), "routes")}
    for host in API_HOSTS:
        pattern = f"{host}/h5p*"
        if pattern in routes:
            print(f"ok   route {pattern}")
            continue
        act(f"add  route {pattern} -> ulams-h5p-proxy", lambda p=pattern: must(call("POST", f"/zones/{ZONE}/workers/routes", {"pattern": p, "script": "ulams-h5p-proxy"}), p))


def ruleset(phase):
    for r in must(call("GET", f"/zones/{ZONE}/rulesets"), "rulesets"):
        if r["phase"] == phase:
            return must(call("GET", f"/zones/{ZONE}/rulesets/{r['id']}"), phase)
    return None


def put_rules(phase, rules, name="default"):
    existing = ruleset(phase)
    if existing:
        return must(call("PUT", f"/zones/{ZONE}/rulesets/{existing['id']}", {"rules": rules}), phase)
    return must(call("PUT", f"/zones/{ZONE}/rulesets/phases/{phase}/entrypoint", {"name": name, "rules": rules}), phase)


def keep(rules):
    return [{k: v for k, v in r.items() if k in ("action", "action_parameters", "description", "enabled", "expression")} for r in rules]


def step_rules():
    landing = f'(http.host ne "{ZONE_NAME}" and http.host ne "www.{ZONE_NAME}")'
    robots = {"description": "ulams: no indexing except the landing", "enabled": True, "action": "rewrite", "expression": landing,
              "action_parameters": {"headers": {"X-Robots-Tag": {"operation": "set", "value": "noindex, nofollow, noarchive"}}}}
    strip = {"description": "ulams: strip X-Ulams-Content-Origin on API hosts (only the content Worker may set it)", "enabled": True, "action": "rewrite",
             "expression": f'(http.host eq "api.{ZONE_NAME}" or ends_with(http.host, "-api.{ZONE_NAME}"))',
             "action_parameters": {"headers": {"X-Ulams-Content-Origin": {"operation": "remove"}}}}
    for phase, rule in (("http_response_headers_transform", robots), ("http_request_late_transform", strip)):
        current = ruleset(phase)
        rules = keep(current["rules"]) if current else []
        if any(r.get("description") == rule["description"] for r in rules):
            rules = [rule if r.get("description") == rule["description"] else r for r in rules]
            print(f"ok   rule '{rule['description']}' (refreshed)")
        else:
            rules.append(rule)
        act(f"set  {phase}: {len(rules)} rules", lambda p=phase, r=rules: put_rules(p, r))


def step_redirects():
    z = ZONE_NAME
    def rule(desc, pattern, target, code):
        return {"description": desc, "enabled": True, "action": "redirect", "expression": f'(http.request.full_uri wildcard "{pattern}")',
                "action_parameters": {"from_value": {"status_code": code, "preserve_query_string": False,
                                                      "target_url": {"expression": f'wildcard_replace(http.request.full_uri, "{pattern}", "{target}")'}}}}
    rules = [
        rule("old landing -> apex", f"https://staging.{z}/*", f"https://{z}/${{1}}", 301),
        rule("www -> apex", f"https://www.{z}/*", f"https://{z}/${{1}}", 301),
        rule("old tenant fronts", f"https://*-staging.{z}/*", f"https://${{1}}.{z}/${{2}}", 301),
        rule("old tenant api/admin/content", f"https://*-staging-*.{z}/*", f"https://${{1}}-${{2}}.{z}/${{3}}", 308),
        rule("old platform api/admin/content/storage/h5p/pdf", f"https://staging-*.{z}/*", f"https://${{1}}.{z}/${{2}}", 308),
    ]
    act(f"set  http_request_dynamic_redirect: {len(rules)} rules", lambda: put_rules("http_request_dynamic_redirect", rules))


STEPS = {"dns": step_dns, "pages": step_pages, "workers": step_workers, "rules": step_rules, "redirects": step_redirects}
if len(sys.argv) < 2 or sys.argv[1] not in STEPS:
    sys.exit(__doc__)
STEPS[sys.argv[1]]()
