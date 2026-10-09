#!/usr/bin/env node
/**
 * `max` with the Node 24 compatibility shim (see node-compat.cjs, ADR 0070). Every `max` script
 * of package.json goes through here, including the `max setup` postinstall.
 */
const path = require('node:path');

const shim = path.join(__dirname, 'node-compat.cjs');
require(shim);

// umi starts worker processes: they need the shim too
const options = process.env.NODE_OPTIONS ? `${process.env.NODE_OPTIONS} ` : '';
process.env.NODE_OPTIONS = `${options}--require ${JSON.stringify(shim)}`;

require('@umijs/max/bin/max.js');
