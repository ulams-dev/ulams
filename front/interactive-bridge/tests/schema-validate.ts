// A tiny JSON Schema subset validator for the tests (no dependencies).
type S = Record<string, unknown>;

export function validate(schema: S, value: unknown): string[] {
  const errors: string[] = [];
  const walk = (s: S, v: unknown, at: string) => {
    if ("const" in s && v !== s.const) errors.push(`${at}: const`);
    if (Array.isArray(s.enum) && !s.enum.includes(v)) errors.push(`${at}: enum`);
    const t = s.type as string | undefined;
    if (t === "string") {
      if (typeof v !== "string") return void errors.push(`${at}: not a string`);
      if (typeof s.minLength === "number" && v.length < s.minLength) errors.push(`${at}: minLength`);
      if (typeof s.maxLength === "number" && v.length > s.maxLength) errors.push(`${at}: maxLength`);
      if (typeof s.pattern === "string" && !new RegExp(s.pattern).test(v)) errors.push(`${at}: pattern`);
    } else if (t === "number" || t === "integer") {
      if (typeof v !== "number") return void errors.push(`${at}: not a number`);
      if (typeof s.minimum === "number" && v < s.minimum) errors.push(`${at}: minimum`);
      if (typeof s.maximum === "number" && v > s.maximum) errors.push(`${at}: maximum`);
      if (typeof s.exclusiveMinimum === "number" && v <= s.exclusiveMinimum) errors.push(`${at}: exclusiveMinimum`);
    } else if (t === "boolean") {
      if (typeof v !== "boolean") errors.push(`${at}: not a boolean`);
    } else if (t === "array") {
      if (!Array.isArray(v)) return void errors.push(`${at}: not an array`);
      if (typeof s.maxItems === "number" && v.length > s.maxItems) errors.push(`${at}: maxItems`);
      if (typeof s.minItems === "number" && v.length < s.minItems) errors.push(`${at}: minItems`);
      if (s.items) v.forEach((x, i) => walk(s.items as S, x, `${at}[${i}]`));
    } else if (t === "object") {
      if (typeof v !== "object" || v === null || Array.isArray(v)) return void errors.push(`${at}: not an object`);
      const o = v as Record<string, unknown>;
      for (const k of (s.required as string[] | undefined) ?? []) if (!(k in o)) errors.push(`${at}.${k}: required`);
      const props = (s.properties as Record<string, S> | undefined) ?? {};
      for (const [k, x] of Object.entries(o)) {
        if (props[k]) walk(props[k], x, `${at}.${k}`);
        else if (s.additionalProperties === false) errors.push(`${at}.${k}: additional`);
        else if (s.additionalProperties && typeof s.additionalProperties === "object") walk(s.additionalProperties as S, x, `${at}.${k}`);
        if (s.propertyNames && !new RegExp(((s.propertyNames as S).pattern as string) ?? "").test(k)) errors.push(`${at}.${k}: propertyName`);
      }
      if (typeof s.maxProperties === "number" && Object.keys(o).length > s.maxProperties) errors.push(`${at}: maxProperties`);
    }
  };
  walk(schema, value, "$");
  return errors;
}
