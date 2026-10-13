import http from "node:http";
import process from "node:process";
import { Buffer } from "node:buffer";
import console from "node:console";
const { fetch } = globalThis;
import assert from 'node:assert/strict';
const token=process.env.PDF_INTERNAL_TOKEN;
const template={basePdf:{width:210,height:297,padding:[10,10,10,10]},schemas:[[{name:'message',type:'text',position:{x:20,y:20},width:150,height:20,fontSize:18,fontName:'NotoSans-Regular',content:'ULAMS certificate',readOnly:false}]]};
const pdf=await fetch('http://127.0.0.1:3000/render',{method:'POST',headers:{'Content-Type':'application/json','X-Internal-Token':token},body:JSON.stringify({template,inputs:[{message:'ULAMS certificate'}]})});
assert.equal(pdf.status,200);const bytes=Buffer.from(await pdf.arrayBuffer());assert.equal(bytes.subarray(0,5).toString(),'%PDF-');
const denied=await fetch('http://127.0.0.1:3000/render',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});assert.equal(denied.status,401);
const mjml=await fetch('http://mjml:15500/v1/render',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mjml:'<mjml><mj-body><mj-section><mj-column><mj-text>ULAMS email</mj-text></mj-column></mj-section></mj-body></mjml>'})});assert.equal(mjml.status,200);assert.match((await mjml.json()).html,/ULAMS email/);
const h5p=await new Promise((resolve,reject)=>{
  const request=http.get({hostname:'h5p',port:8080,path:'/h5p/health',headers:{Host:'api.ulams.localhost'},agent:false},response=>{
    let body='';response.on('data',chunk=>body+=chunk);response.on('end',()=>resolve({status:response.statusCode,body:JSON.parse(body)}));
  });request.on('error',reject);
});assert.equal(h5p.status,200);assert.equal(h5p.body.ok,true);
console.log(JSON.stringify({pdfBytes:bytes.length,pdfUnauthorizedRejected:true,mjmlRendered:true,h5pHealthy:true}));
