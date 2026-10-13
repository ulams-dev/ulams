import { request as httpRequest } from "node:http";
import { request as httpsRequest } from "node:https";
import { Readable } from "node:stream";

/** RFC 6761 localhost subdomains must work without editing /etc/hosts, including in Node. */
export const loopbackFetch: typeof fetch = async (input,init) => {
  const request = new Request(input,init), url = new URL(request.url);
  if(!url.hostname.endsWith('.localhost'))return fetch(request);
  const body = request.body ? Buffer.from(await request.arrayBuffer()) : undefined;
  return new Promise((resolve,reject)=>{
    const headers = Object.fromEntries(request.headers);
    if(body) headers['content-length'] = String(body.length);
    const outgoing = (url.protocol === 'https:' ? httpsRequest : httpRequest)(url, {
      method: request.method, headers, signal: request.signal, agent: false,
      lookup: (_host,options,callback) => {
        if(typeof options === 'object' && options.all) callback(null,[{address:'127.0.0.1',family:4}]);
        else callback(null,'127.0.0.1',4);
      },
    }, async incoming=>{
      try {
      const responseHeaders = new Headers();
      for(let i=0;i<incoming.rawHeaders.length;i+=2)responseHeaders.append(incoming.rawHeaders[i]!,incoming.rawHeaders[i+1]!);
      const empty = request.method === 'HEAD' || [204,304].includes(incoming.statusCode ?? 200);
      const streamed = responseHeaders.get('content-type')?.includes('text/event-stream');
      if(empty)incoming.resume();
      const chunks: Buffer[] = [];
      if(!empty && !streamed)for await(const chunk of incoming)chunks.push(Buffer.from(chunk));
      // Buffer-backed responses avoid keeping an unconsumed clone's MessagePort alive.
      const content = empty ? null : streamed ? Readable.toWeb(incoming) as ReadableStream<Uint8Array> : new Uint8Array(Buffer.concat(chunks));
      resolve(new Response(content,{status:incoming.statusCode,statusText:incoming.statusMessage,headers:responseHeaders}));
      } catch(error) { reject(error); }
    });
    outgoing.on('error',reject);outgoing.end(body);
  });
};
