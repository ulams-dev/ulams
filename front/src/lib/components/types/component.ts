/** Components that accept a class name (formerly: extendable with `styled(Component)`). */
export interface ExtendableStyledComponent {
  className?: string;
}

/**
 * Props of the former `withTheme(styled(Component))` default exports: their typing
 * accepted any extra or loosely typed prop, so the default exports keep doing so.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type LegacyProps<P> = { [K in keyof P]?: any } & Record<string, any>;
