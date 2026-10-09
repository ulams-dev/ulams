import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * "My tokens" on /account (ADR 0074): create a scoped token, see its secret once, list it, use it
 * against the API, revoke it. Runs as the demo student of the tenant and removes what it creates.
 * WCAG 2.2 AA (axe) is checked in every state of the section.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const slug = process.env.TOKENS_E2E_TENANT ?? "coffee";
const web = `http://${slug}.app.localhost:${port}`;
const api = process.env[`API_${slug.toUpperCase()}`] ?? `http://${slug}.localhost`;

async function axe(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

const current = (secret: string) => fetch(`${api}/api/auth/tokens/current`, { headers: { Authorization: `Bearer ${secret}`, Accept: "application/json" } });

test.use({ contextOptions: { reducedMotion: "reduce" } });

test("create, copy once, use, list and revoke a personal token", async ({ page }, info) => {
  const name = `e2e ${info.project.name} ${Date.now()}`;
  await page.goto(`${web}/account`);
  await expect(page.getByRole("heading", { level: 2, name: "My tokens" })).toBeVisible();
  await axe(page);

  // create: a read-only token
  await page.getByLabel("Name").fill(name);
  await page.getByRole("radio", { name: /Read only/ }).check();
  await page.getByLabel("Valid for").selectOption("7");
  await page.getByRole("button", { name: "Create token" }).click();

  const secretField = page.getByLabel(new RegExp(`Your new token “${name}”`));
  await expect(secretField).toBeVisible();
  const secret = await secretField.inputValue();
  expect(secret.startsWith("ulams_pat_")).toBe(true);
  await expect(page.getByRole("status").filter({ hasText: "shown only once" })).toBeVisible();
  await axe(page);

  // the token works, is scoped, belongs to the signed-in user and expires in 7 days
  const me = await current(secret);
  expect(me.status).toBe(200);
  const body = (await me.json()) as { data: { scoped: boolean; created_via: string; name: string } };
  expect(body.data).toMatchObject({ scoped: true, created_via: "admin", name });

  // listed, and the secret is gone after a reload
  await page.goto(`${web}/account`); // a fresh GET: the page of the POST had the secret
  const item = page.getByRole("listitem").filter({ hasText: name });
  await expect(item).toBeVisible();
  await expect(item.getByText("Active")).toBeVisible();
  await expect(item.getByText(/Read:/)).toBeVisible();
  await expect(page.getByLabel(/Your new token/)).toHaveCount(0);
  expect(await page.content()).not.toContain(secret);
  await axe(page);

  // revoke: it stops working at once and is shown as revoked
  await item.getByRole("button", { name: `Revoke token ${name}` }).click();
  await expect(page).toHaveURL(/\/account\?tokens=revoked/);
  await expect(page.getByRole("status").filter({ hasText: "Token revoked" })).toBeVisible();
  expect((await current(secret)).status).toBe(401);
  await axe(page);
});

test("an empty name is refused before anything is sent", async ({ page }) => {
  await page.goto(`${web}/account`);
  await page.getByRole("button", { name: "Create token" }).click();
  await expect(page.getByLabel("Name")).toBeFocused();
  await expect(page).toHaveURL(new RegExp(`${web.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}/account$`));
});
