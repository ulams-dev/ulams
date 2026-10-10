// Downloads the four photographs of the Ulam course from Wikimedia Commons and writes them as WebP next to the seeder
// (api/database/seeds/Demo/assets/ulam/images/), where the API container can read them. Dev only, output committed:
//   node demo-content/ulam/scripts/fetch-images.mjs
// Each file's licence was read on its Commons page (docs/plans/interactive-demos-ulam-facts.md, section 10); the credit
// lines the course shows are in the lessons and in demo-content/ulam/CREDITS.md. Nothing is fetched at seed time.
import { mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const out = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "..", "api", "database", "seeds", "Demo", "assets", "ulam", "images");
const UA = "ulams-course-build/1.0 (https://github.com/ulams-dev/ulams)";

/** name: [Commons file page, original file, output width] */
const IMAGES = {
  "ulam-badge": ["https://commons.wikimedia.org/wiki/File:Ulam-stanislaw_m.jpg", "https://upload.wikimedia.org/wikipedia/commons/0/0a/Ulam-stanislaw_m.jpg", 360],
  "ulam-portrait": ["https://commons.wikimedia.org/wiki/File:Stanislaw_Ulam.tif", "https://upload.wikimedia.org/wikipedia/commons/8/82/Stanislaw_Ulam.tif", 360],
  "fermiac": ["https://commons.wikimedia.org/wiki/File:Fermiac.jpg", "https://upload.wikimedia.org/wikipedia/commons/f/f0/Fermiac.jpg", 240],
  "scottish-cafe-building": ["https://commons.wikimedia.org/wiki/File:Lviv_Shevchenka_Pr_27_RB.jpg", "https://upload.wikimedia.org/wikipedia/commons/3/39/Lviv_Shevchenka_Pr_27_RB.jpg", 400],
};

mkdirSync(out, { recursive: true });
for (const [name, [page, url, width]] of Object.entries(IMAGES)) {
  const res = await fetch(url, { headers: { "user-agent": UA } });
  if (!res.ok) throw new Error(`${url}: ${res.status}`);
  const file = join(out, `${name}.webp`);
  const info = await sharp(Buffer.from(await res.arrayBuffer())).rotate().resize({ width, withoutEnlargement: true }).webp({ quality: 80, effort: 5 }).toFile(file);
  console.log(`${name}.webp ${info.width}x${info.height} ${Math.round(info.size / 1024)} KB  <- ${page}`);
}
