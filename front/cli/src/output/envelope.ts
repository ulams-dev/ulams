import { CONTRACT, type Result, type Warning } from "../registry/types.ts";
import { toErrorObject, type CliError, type ErrorObject } from "../errors.ts";

export interface SuccessEnvelope {
  ok: true;
  contract: number;
  command: string;
  data: unknown;
  meta?: unknown;
  warnings?: Warning[];
}

export interface ErrorEnvelope {
  ok: false;
  contract: number;
  command: string;
  error: ErrorObject;
}

export type Envelope = SuccessEnvelope | ErrorEnvelope;

export function successEnvelope(command: string, result: Result): SuccessEnvelope {
  return {
    ok: true,
    contract: CONTRACT,
    command,
    data: result.data === undefined ? null : result.data,
    ...(result.meta ? { meta: result.meta } : {}),
    ...(result.warnings && result.warnings.length ? { warnings: result.warnings } : {}),
  };
}

export function errorEnvelope(command: string, error: CliError): ErrorEnvelope {
  return { ok: false, contract: CONTRACT, command, error: toErrorObject(error) };
}

/** JSON Schema of the envelope (ADR 0073), included in `ulams schema`. */
export const ENVELOPE_SCHEMA = {
  $schema: "https://json-schema.org/draft/2020-12/schema",
  $id: "https://ulams.dev/schemas/cli-envelope/v1.json",
  oneOf: [
    {
      type: "object",
      required: ["ok", "contract", "command", "data"],
      properties: {
        ok: { const: true },
        contract: { type: "integer" },
        command: { type: "string" },
        data: {},
        meta: {
          type: "object",
          properties: {
            page: { type: "integer" },
            perPage: { type: "integer" },
            total: { type: "integer" },
            lastPage: { type: "integer" },
            nextPage: { type: ["integer", "null"] },
          },
        },
        warnings: {
          type: "array",
          items: {
            type: "object",
            required: ["code", "message"],
            properties: { code: { type: "string" }, message: { type: "string" }, hint: { type: "string" } },
          },
        },
      },
    },
    {
      type: "object",
      required: ["ok", "contract", "command", "error"],
      properties: {
        ok: { const: false },
        contract: { type: "integer" },
        command: { type: "string" },
        error: {
          type: "object",
          required: ["code", "message", "hint", "status", "retryable", "requestId", "details"],
          properties: {
            code: { type: "string" },
            message: { type: "string" },
            hint: { type: "string" },
            status: { type: ["integer", "null"] },
            retryable: { type: "boolean" },
            requestId: { type: ["string", "null"] },
            details: { type: "object" },
          },
        },
      },
    },
  ],
} as const;
