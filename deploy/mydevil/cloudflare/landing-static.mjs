#!/usr/bin/env node
// Renders the platform landing (/, /pl/, /zh/) of the Astro SSR front into a static folder for
// Cloudflare Pages. Run `corepack yarn workspace @ulams/web build` first, then:
//
//   node deploy/mydevil/cloudflare/landing-static.mjs <out-dir> [platform-host]
//
// The build env of the staging front is read from the environment (ULAMS_PLATFORM_HOSTS,
// ULAMS_TENANT_HOSTS, ULAMS_DEMO_TENANTS, ULAMS_CONTENT_ORIGIN, ULAMS_STORAGE_ORIGINS,
// ULAMS_ADMIN_URL), so the demo cards come out the same as the SSR site's.
import { spawn } from 'node:child_process'
import { cpSync, mkdirSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const out = resolve(process.argv[2] ?? '')
const host = process.argv[3] ?? 'staging.ulams.app'
if (!process.argv[2]) throw new Error('usage: landing-static.mjs <out-dir> [platform-host]')
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')
const dist = join(root, 'front/web/dist')
const port = 4399
const pages = ['/', '/pl/', '/zh/']

const server = spawn('node', [join(dist, 'server/entry.mjs')], {
  env: { ...process.env, HOST: '127.0.0.1', PORT: String(port) },
  stdio: 'inherit',
})
try {
  for (let i = 0; ; i++) {
    try { await fetch(`http://127.0.0.1:${port}/`, { headers: { host } }); break } catch {
      if (i > 50) throw new Error('the SSR server did not start')
      await new Promise((r) => setTimeout(r, 200))
    }
  }
  rmSync(out, { recursive: true, force: true })
  cpSync(join(dist, 'client'), out, { recursive: true })
  for (const path of pages) {
    const res = await fetch(`http://127.0.0.1:${port}${path}`, { headers: { host } })
    if (!res.ok) throw new Error(`${path} -> ${res.status}`)
    // The staging front uses the noop image service: /_image?href=<url>&w=.. is just the original.
    const html = (await res.text()).replace(/\/_image\?href=([^&"')\s]+)[^"')\s]*/g, (_, h) => decodeURIComponent(h.replaceAll('&amp;', '&')))
    mkdirSync(join(out, path), { recursive: true })
    writeFileSync(join(out, path, 'index.html'), html)
  }
  writeFileSync(join(out, 'robots.txt'), 'User-agent: *\nDisallow: /\n')
  writeFileSync(join(out, '_headers'), [
    '/*', '  X-Robots-Tag: noindex, nofollow', '  X-Content-Type-Options: nosniff', '  Referrer-Policy: strict-origin-when-cross-origin',
    '/_astro/*', '  Cache-Control: public, max-age=31536000, immutable',
    '/landing/*', '  Cache-Control: public, max-age=86400', '',
  ].join('\n'))
  console.log(`wrote ${pages.length} pages and the client assets to ${out}`)
} finally {
  server.kill()
}
