import { ApiError, type UlamsClient } from "./client.ts";
import type { LoginResult } from "./types.ts";

export interface DemoSessionOptions {
  /** Fallback account when demo mode is off on the tenant (password login). */
  fallback?: { email: string; password: string } | null;
}

export interface DemoSession extends LoginResult {
  /** How the session was obtained. */
  via: "demo" | "password";
}

/**
 * Logs in as the tenant's demo student: `POST /api/demo/login` when demo mode is on,
 * otherwise a password login with the fallback account. Throws the last `ApiError`.
 */
export async function demoStudentSession(client: UlamsClient, options: DemoSessionOptions = {}): Promise<DemoSession> {
  try {
    const result = await client.auth.demoLogin("student");
    return { ...result, via: "demo" };
  } catch (error) {
    const demoOff = error instanceof ApiError && (error.status === 404 || error.status === 405);
    if (!demoOff || !options.fallback) throw error;
  }
  const result = await client.auth.login(options.fallback.email, options.fallback.password);
  return { ...result, via: "password" };
}
