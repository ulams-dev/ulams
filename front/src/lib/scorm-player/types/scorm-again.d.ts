// src/types/scorm-again.d.ts
declare module "scorm-again" {
  // If you only need the bare minimum, you can do:
  //   export class Scorm2004API { constructor(...args: any[]); }
  //   export class Scorm12API { ... }
  //   etc.

  export class Scorm12API {
    constructor(settings: any);
    // define methods if needed
  }

  export class Scorm2004API {
    constructor(settings: any);
    // define methods if needed
  }

  export class AICC {
    constructor(settings: any);
    // define methods if needed
  }
}
