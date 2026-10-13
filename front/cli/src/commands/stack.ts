import { z } from "zod";
import { defineCommand } from "./define.ts";
import { stackInput } from "../stack/config.ts";
import { installDirectory, readConfig } from "../stack/files.ts";
import { stackDown, stackPlan, stackRender, stackStatus, stackUp } from "../stack/runtime.ts";
const common={since:"0.2.0",anonymous:true,mcp:{expose:false},idempotent:true,output:z.unknown()};
export const stackCommands=[
  defineCommand({...common,id:"stack.up",kind:"write",input:stackInput,summary:"Launch or resume a complete development/production stack with Docker or Kubernetes",plan:async(_ctx,i)=>stackPlan(stackInput.parse(i)),run:async(ctx,i)=>({data:await stackUp(ctx,stackInput.parse(i))}),examples:[{title:"Local Phase 2 trial",argv:"stack up"},{title:"Server",argv:"stack up --mode production --domain learn.example.org"},{title:"Kubernetes",argv:"stack up --target kubernetes --mode production --domain learn.example.org"}]}),
  defineCommand({...common,id:"stack.render",kind:"write",input:stackInput,summary:"Generate inspectable deployment files and private credentials without starting services",plan:async(_ctx,i)=>stackPlan(stackInput.parse(i)),run:async(ctx,i)=>({data:await stackRender(ctx,stackInput.parse(i))}),examples:[{title:"Inspect Kubernetes",argv:"stack render --target kubernetes --directory ./my-stack"}]}),
  defineCommand({...common,id:"stack.status",kind:"read",input:z.object({directory:z.string().optional()}),summary:"Show resources and URLs of this CLI-owned stack",run:async(ctx,i)=>({data:await stackStatus(ctx,i.directory as string|undefined)}),examples:[{title:"Status",argv:"stack status --json"}]}),
  defineCommand({...common,id:"stack.down",kind:"write",kindFor:i=>i.purge?"destructive":"write",input:z.object({directory:z.string().optional(),purge:z.boolean().default(false).describe("Explicitly delete owned persistent data; requires --yes.")}),summary:"Stop this stack, keeping persistent data unless --purge is requested",plan:async(_ctx,i)=>({directory:installDirectory(i.directory as string|undefined),config:await readConfig(installDirectory(i.directory as string|undefined)),deletePersistentData:Boolean(i.purge)}),run:async(ctx,i)=>({data:await stackDown(ctx,i.directory as string|undefined,Boolean(i.purge))}),examples:[{title:"Keep data",argv:"stack down"},{title:"Delete own data",argv:"stack down --purge --yes"}]}),
];
