import type * as React from "react";
import type { LegacyProps } from "../types/component";

/** Types a component like the former `withTheme(styled(Component))` default export (see LegacyProps). */
export const legacyDefault = <P,>(
  component: React.ComponentType<P>
): React.FC<LegacyProps<P>> => component as unknown as React.FC<LegacyProps<P>>;
