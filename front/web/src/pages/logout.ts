import type { APIRoute } from "astro";
import { clearSessionCookie } from "../lib/session.ts";

export const POST: APIRoute = ({ cookies, redirect, locals }) => {
  clearSessionCookie(cookies, locals.secure);
  return redirect("/", 303);
};
