import type { APIRoute } from "astro";
import { clearSessionCookie } from "../lib/session.ts";

export const POST: APIRoute = ({ cookies, redirect }) => {
  clearSessionCookie(cookies);
  return redirect("/", 303);
};
