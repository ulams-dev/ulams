import { renderToStaticMarkup } from '@usewaypoint/email-builder';
const doc = (children, extra = {}) => ({
  root: { type: 'EmailLayout', data: { backdropColor: '#F5F5F5', canvasColor: '#FFFFFF', textColor: '#242424', fontFamily: 'MODERN_SANS', childrenIds: children.map(c => c.id) } },
  ...Object.fromEntries(children.map(c => [c.id, { type: c.type, data: c.data }])), ...extra,
});
// Template 1 (reset password) mapped by hand
const t1 = doc([
  { id: 'a', type: 'Text', data: { props: { text: 'You are receiving this email because we received a password reset request for your account.' }, style: {} } },
  { id: 'b', type: 'Button', data: { props: { text: 'Reset Password', url: '@VarActionLink' }, style: {} } },
  { id: 'c', type: 'Text', data: { props: { text: 'This password reset link will expire in @VarActionLinkExpiration minutes.' }, style: {} } },
]);
// Template 3 (branded) best-effort mapping: no mj-head, mj-attributes/mj-class/mj-style, lang, preview text, conditional comments
const t3 = doc([
  { id: 'i', type: 'Image', data: { props: { url: 'https://example.com/logo.png', alt: 'Academy logo', width: 120 }, style: {} } },
  { id: 'h', type: 'Heading', data: { props: { text: 'Cześć ${VarUserName}!', level: 'h1' }, style: {} } },
  { id: 't', type: 'Text', data: { props: { text: 'Twoje konto w akademii jest gotowe. 欢迎加入!' }, style: {} } },
  { id: 'r', type: 'Html', data: { props: { contents: '<table role="presentation" width="100%"><tr><td style="background:#eef2ff;padding:12px">Raw HTML promo block: <strong>-20%</strong></td></tr></table>' }, style: {} } },
  { id: 'b', type: 'Button', data: { props: { text: 'Otwórz kurs', url: '@VarActionLink' }, style: {} } },
  { id: 'f', type: 'Text', data: { props: { text: '@GlobalSettingsMailFooterMjml' }, style: {} } },
]);
for (const [n, d] of [['t1', t1], ['t3', t3]]) {
  const html = renderToStaticMarkup(d, { rootBlockId: 'root' });
  console.log(n, html.length, 'bytes; has lang attr:', /<html[^>]*lang=/.test(html), '; mso conditional:', html.includes('[if mso]'), '; contains @VarActionLink:', html.includes('@VarActionLink'), '; ${VarUserName}:', html.includes('${VarUserName}'), '; footer token:', html.includes('@GlobalSettings'));
}
console.log(renderToStaticMarkup(t1, { rootBlockId: 'root' }).slice(0, 600));
