import { homedir } from "node:os";
import { join } from "node:path";

export function configDir(env: NodeJS.ProcessEnv = process.env, platform: string = process.platform): string {
  if (env.ULAMS_CONFIG_DIR) return env.ULAMS_CONFIG_DIR;
  if (platform === "win32") return join(env.APPDATA ?? join(homedir(), "AppData", "Roaming"), "ulams");
  if (env.XDG_CONFIG_HOME) return join(env.XDG_CONFIG_HOME, "ulams");
  return join(homedir(), ".config", "ulams");
}

export const configFile = (dir: string): string => join(dir, "config.json");
export const credentialsFile = (dir: string): string => join(dir, "credentials.json");
