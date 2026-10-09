// Astro components imported by the render helper (the container API takes any component factory).
declare module "*.astro" {
  const component: (props: never) => unknown;
  export default component;
}
