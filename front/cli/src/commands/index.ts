import type { AnyCommand } from "../registry/types.ts";
import { api } from "./core/api.ts";
import { completion } from "./core/completion.ts";
import { describeCommand, schemaCommand, version } from "./core/introspect.ts";
import { login } from "./core/login.ts";
import { configGet, configSet, profilesDelete, profilesList, profilesUse } from "./core/profiles.ts";
import { logout, whoami } from "./core/session.ts";

export const handWritten: AnyCommand[] = [
  login,
  logout,
  whoami,
  profilesList,
  profilesUse,
  profilesDelete,
  configGet,
  configSet,
  api,
  schemaCommand,
  describeCommand,
  version,
  completion,
];
