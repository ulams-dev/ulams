#!/usr/bin/env node
// Renders the platform landing (/, /pl/, /zh/) of the Astro SSR front into a static folder for
// Cloudflare Pages. Run `corepack yarn workspace @ulams/web build` first, then:
//
//   node deploy/mydevil/cloudflare/landing-static.mjs <out-dir> [platform-host]   (host: ulams.app for production)
//
// The build env of the staging front is read from the environment (ULAMS_PLATFORM_HOSTS,
// ULAMS_TENANT_HOSTS, ULAMS_DEMO_TENANTS, ULAMS_CONTENT_ORIGIN, ULAMS_STORAGE_ORIGINS,
// ULAMS_ADMIN_URL), so the demo cards come out the same as the SSR site's.
import { spawn } from 'node:child_process'
import { request } from 'node:http'
import { cpSync, mkdirSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const out = resolve(process.argv[2] ?? '')
const host = process.argv[3] ?? 'ulams.app'
if (!process.argv[2]) throw new Error('usage: landing-static.mjs <out-dir> [platform-host]')
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')
const dist = join(root, 'front/web/dist')
const port = 4399
const pages = ['/', '/pl/', '/zh/']

// node:http, not fetch: fetch silently drops a custom Host header, and the front picks the site by Host.
const get = (path) => new Promise((resolveGet, reject) => {
  request({ host: '127.0.0.1', port, path, headers: { host, 'x-forwarded-host': host, 'x-forwarded-proto': 'https' } }, (res) => {
    let body = ''
    res.setEncoding('utf8').on('data', (c) => (body += c)).on('end', () => resolveGet({ status: res.statusCode, body }))
  }).on('error', reject).end()
})

const server = spawn('node', [join(dist, 'server/entry.mjs')], {
  env: { ...process.env, HOST: '127.0.0.1', PORT: String(port) },
  stdio: 'inherit',
})
try {
  for (let i = 0; ; i++) {
    try { await get('/'); break } catch {
      if (i > 50) throw new Error('the SSR server did not start')
      await new Promise((r) => setTimeout(r, 200))
    }
  }
  rmSync(out, { recursive: true, force: true })
  cpSync(join(dist, 'client'), out, { recursive: true })
  for (const path of pages) {
    // The front reads each demo tenant's API with a 2.5 s budget and caches it: the first request is
    // cold, so retry until the cards carry their course links (a /learn/ link) before taking the page.
    let res = await get(path)
    for (let i = 0; i < 20 && res.status === 200 && !res.body.includes('/learn/'); i++) {
      await new Promise((r) => setTimeout(r, 1500))
      res = await get(path)
    }
    if (!res.body.includes('/learn/')) throw new Error(`${path}: the demo cards have no course links (tenant APIs unreachable?)`)
    if (res.status !== 200) throw new Error(`${path} -> ${res.status}`)
    // The staging front uses the noop image service: /_image?href=<url>&w=.. is just the original.
    // The render server is plain http on a local port; the published origin is https on the real host.
    const html = res.body.replaceAll(`http://localhost:${port}`, `https://${host}`).replaceAll(`:${port}`, '').replace(/http:\/\/([a-z0-9.-]+\.ulams\.app)/g, 'https://$1').replace(/\/_image\?href=([^&"')\s]+)[^"')\s]*/g, (_, h) => decodeURIComponent(h.replaceAll('&amp;', '&')))
    mkdirSync(join(out, path), { recursive: true })
    writeFileSync(join(out, path, 'index.html'), html)
  }
  // staging.* is never indexed; any other host is a production landing: crawlable, with a sitemap and no noindex header
  const production = !host.startsWith('staging.')
  const security = ['  X-Content-Type-Options: nosniff', '  Referrer-Policy: strict-origin-when-cross-origin']
  if (production) {
    const origin = `https://${host}`
    const alternates = [['en', '/'], ['pl', '/pl/'], ['zh', '/zh/']]
    const links = alternates.map(([lang, path]) => `    <xhtml:link rel="alternate" hreflang="${lang}" href="${origin}${path}"/>`).join('\n')
    writeFileSync(join(out, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${origin}/sitemap.xml\n`)
    writeFileSync(join(out, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${alternates.map(([, path]) => `  <url>\n    <loc>${origin}${path}</loc>\n${links}\n  </url>`).join('\n')}\n</urlset>\n`)
  } else {
    writeFileSync(join(out, 'robots.txt'), 'User-agent: *\nDisallow: /\n')
    security.unshift('  X-Robots-Tag: noindex, nofollow')
  }
  writeFileSync(join(out, '_headers'), [
    '/*', ...security,
    '/_astro/*', '  Cache-Control: public, max-age=31536000, immutable',
    '/landing/*', '  Cache-Control: public, max-age=86400', '',
  ].join('\n'))
  console.log(`wrote ${pages.length} pages and the client assets to ${out}`)
} finally {
  server.kill()
}
