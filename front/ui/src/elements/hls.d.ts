// hls.js ships types for its main entry only; the light build has the same API.
declare module "hls.js/dist/hls.light.min.mjs" {
  import Hls from "hls.js";
  export default Hls;
}
