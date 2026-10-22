export type Severity = "error" | "warning" | "info";
export type Obj = Record<string, unknown>;
export type Path = Array<string | number>;
export interface Pos {
  line: number;
  column: number;
}

/** The text files of a course folder, keyed by posix path relative to the folder root. */
export type Files = Map<string, string>;

export interface AssetInfo {
  bytes: number;
  sha256: string;
}
/** Resolves a path relative to the course folder (`assets/...`); null when the file does not exist. */
export type AssetLookup = (path: string) => AssetInfo | null;

export interface Issue {
  code: string;
  severity: Severity;
  file: string;
  line: number;
  column: number;
  element?: string;
  message: string;
  hint: string;
}

export const BLOCK_KINDS = ["paragraph", "callout", "steps", "code", "table", "example"] as const;
export type BlockKind = (typeof BLOCK_KINDS)[number];

export const isObj = (v: unknown): v is Obj => typeof v === "object" && v !== null && !Array.isArray(v);
