<?php

namespace Ulams\Identity\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * A SAML identity provider in memory: metadata, signed responses (assertion, response or both, any algorithm),
 * encrypted assertions, logout messages in both bindings, and the well-known attacks as mutations of a signed
 * document. Locally generated RSA keys and certificates; nothing leaves the process.
 */
class FakeSamlIdp
{
    public const ENTITY_ID = 'https://idp.example.test/saml/metadata';
    public const SSO_URL = 'https://idp.example.test/saml/sso';
    public const SLO_URL = 'https://idp.example.test/saml/slo';
    public const METADATA_URL = 'https://idp.example.test/saml/metadata';

    /** @var array<string,array{key:string,cert:string}> */
    private static array $pairs = [];

    /** @var list<array{method:string,url:string}> */
    public array $requests = [];
    public ?string $metadataBody = null;
    public int $metadataStatus = 200;
    /** @var array{key:string,cert:string} */
    public array $signing;

    public function __construct(string $name = 'idp')
    {
        $this->signing = self::pair($name);
    }

    /** A key pair and self-signed certificate, generated once per name and process. */
    public static function pair(string $name, int $bits = 2048): array
    {
        $cacheKey = $name . ':' . $bits;
        if (!isset(self::$pairs[$cacheKey])) {
            $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => $name . '.example.test'], $key, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $key, 3650, ['digest_alg' => 'sha256']);
            openssl_pkey_export($key, $pem);
            openssl_x509_export($cert, $certPem);
            self::$pairs[$cacheKey] = ['key' => $pem, 'cert' => $certPem];
        }

        return self::$pairs[$cacheKey];
    }

    public static function certBody(string $pem): string
    {
        return preg_replace('/\s+|-----(?:BEGIN|END) CERTIFICATE-----/', '', $pem);
    }

    /** The HTTP handler for `identity.http_handler`: serves the metadata URL. */
    public function handler(): callable
    {
        return function (RequestInterface $request, array $options) {
            $this->requests[] = ['method' => $request->getMethod(), 'url' => (string) $request->getUri()];
            if ((string) $request->getUri() === self::METADATA_URL) {
                return Create::promiseFor(new Response($this->metadataStatus, ['Content-Type' => 'application/samlmetadata+xml'], $this->metadataBody ?? $this->metadata()));
            }

            return Create::promiseFor(new Response(404, [], 'not found'));
        };
    }

    /**
     * @param list<string>|null $certs PEM certificates to publish (default: the signing certificate)
     */
    public function metadata(?array $certs = null, string $entityId = self::ENTITY_ID, bool $withSlo = true): string
    {
        $keys = '';
        foreach ($certs ?? [$this->signing['cert']] as $pem) {
            $keys .= '<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>' . self::certBody($pem) . '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>';
        }
        $slo = $withSlo ? '<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="' . self::SLO_URL . '"/>' : '';

        return '<?xml version="1.0"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="' . $entityId . '">'
            . '<md:IDPSSODescriptor WantAuthnRequestsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            . $keys . $slo
            . '<md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</md:NameIDFormat>'
            . '<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="' . self::SSO_URL . '"/>'
            . '</md:IDPSSODescriptor></md:EntityDescriptor>';
    }

    /**
     * A SAML response, base64 encoded.
     *
     * @param array{
     *   acs:string, sp:string, request_id:string, name_id?:string, name_id_format?:string, attributes?:array<string,list<string>|string>,
     *   session_index?:?string, assertion_id?:string, issuer?:string, destination?:?string, recipient?:?string, audience?:string|list<string|list<string>>|null,
     *   issued?:int, not_before?:?int, not_on_or_after?:?int, scd_not_on_or_after?:?int, status?:string, sign?:string, key?:array{key:string,cert:string},
     *   sig_alg?:string, digest_alg?:string, encrypt_for?:?string, in_response_to?:?string, response_in_response_to?:?string|false,
     *   name_id_comment?:?string, mutate?:callable(DOMDocument):void, raw?:callable(string):string
     * } $o `sign`: assertion (default), response, both, none
     */
    public function response(array $o): string
    {
        return base64_encode($this->responseXml($o));
    }

    public function responseXml(array $o): string
    {
        $now = time();
        $iso = fn (int $t) => gmdate('Y-m-d\TH:i:s\Z', $t);
        $issued = $o['issued'] ?? $now;
        $nameId = $o['name_id'] ?? 'user-1';
        $format = $o['name_id_format'] ?? 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent';
        $issuer = $o['issuer'] ?? self::ENTITY_ID;
        $audiences = (array) ($o['audience'] ?? $o['sp']);
        $assertionId = $o['assertion_id'] ?? '_a' . bin2hex(random_bytes(8));
        $responseId = '_r' . bin2hex(random_bytes(8));
        $destination = array_key_exists('destination', $o) ? $o['destination'] : $o['acs'];
        $recipient = array_key_exists('recipient', $o) ? $o['recipient'] : $o['acs'];
        $inResponseTo = array_key_exists('in_response_to', $o) ? $o['in_response_to'] : $o['request_id'];
        $responseIn = array_key_exists('response_in_response_to', $o) ? $o['response_in_response_to'] : $o['request_id'];
        $attributes = $o['attributes'] ?? ['email' => ['ada@example.com'], 'givenName' => ['Ada'], 'sn' => ['Lovelace']];
        $sessionIndex = array_key_exists('session_index', $o) ? $o['session_index'] : '_session-1';
        $scdNotOnOrAfter = array_key_exists('scd_not_on_or_after', $o) ? $o['scd_not_on_or_after'] : $now + 300;
        $notBefore = array_key_exists('not_before', $o) ? $o['not_before'] : $now - 5;
        $notOnOrAfter = array_key_exists('not_on_or_after', $o) ? $o['not_on_or_after'] : $now + 300;
        $e = fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_XML1);

        $attrXml = '';
        foreach ($attributes as $name => $values) {
            $attrXml .= '<saml:Attribute Name="' . $e((string) $name) . '">';
            foreach ((array) $values as $v) {
                $attrXml .= '<saml:AttributeValue xsi:type="xs:string">' . $e((string) $v) . '</saml:AttributeValue>';
            }
            $attrXml .= '</saml:Attribute>';
        }
        $nameIdText = $e($nameId);
        if (isset($o['name_id_comment'])) {
            $nameIdText = $e($nameId) . '<!--' . $o['name_id_comment'] . '-->' . $e('.attacker.test');
        }
        // each item is one AudienceRestriction (a list inside it is several audiences of that restriction: any of them)
        $audienceXml = '';
        foreach ($audiences as $restriction) {
            $audienceXml .= '<saml:AudienceRestriction>';
            foreach ((array) $restriction as $a) {
                $audienceXml .= '<saml:Audience>' . $e((string) $a) . '</saml:Audience>';
            }
            $audienceXml .= '</saml:AudienceRestriction>';
        }
        $conditionsAttrs = ($notBefore !== null ? ' NotBefore="' . $iso($notBefore) . '"' : '') . ($notOnOrAfter !== null ? ' NotOnOrAfter="' . $iso($notOnOrAfter) . '"' : '');
        $scdAttrs = ($scdNotOnOrAfter !== null ? ' NotOnOrAfter="' . $iso($scdNotOnOrAfter) . '"' : '') . ($recipient !== null ? ' Recipient="' . $e($recipient) . '"' : '') . ($inResponseTo !== null ? ' InResponseTo="' . $e($inResponseTo) . '"' : '');

        $xml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="' . $responseId . '" Version="2.0" IssueInstant="' . $iso($issued) . '"'
            . ($destination !== null ? ' Destination="' . $e($destination) . '"' : '') . ($responseIn !== null && $responseIn !== false ? ' InResponseTo="' . $e($responseIn) . '"' : '') . '>'
            . '<saml:Issuer>' . $e($issuer) . '</saml:Issuer>'
            . '<samlp:Status><samlp:StatusCode Value="' . ($o['status'] ?? 'urn:oasis:names:tc:SAML:2.0:status:Success') . '"/></samlp:Status>'
            . '<saml:Assertion xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="' . $assertionId . '" Version="2.0" IssueInstant="' . $iso($issued) . '">'
            . '<saml:Issuer>' . $e($issuer) . '</saml:Issuer>'
            . '<saml:Subject><saml:NameID Format="' . $e($format) . '">' . $nameIdText . '</saml:NameID>'
            . '<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData' . $scdAttrs . '/></saml:SubjectConfirmation></saml:Subject>'
            . '<saml:Conditions' . $conditionsAttrs . '>' . $audienceXml . '</saml:Conditions>'
            . '<saml:AuthnStatement AuthnInstant="' . $iso($issued) . '"' . ($sessionIndex !== null ? ' SessionIndex="' . $e($sessionIndex) . '"' : '') . '><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>'
            . ($attrXml !== '' ? '<saml:AttributeStatement>' . $attrXml . '</saml:AttributeStatement>' : '')
            . '</saml:Assertion></samlp:Response>';

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);
        $xp = $this->xpath($dom);
        $assertion = $xp->query('/samlp:Response/saml:Assertion')->item(0);
        $root = $dom->documentElement;
        $key = $o['key'] ?? $this->signing;
        $sigAlg = $o['sig_alg'] ?? XMLSecurityKey::RSA_SHA256;
        $digestAlg = $o['digest_alg'] ?? XMLSecurityDSig::SHA256;
        $sign = $o['sign'] ?? 'assertion';

        if (in_array($sign, ['assertion', 'both'], true)) {
            $this->sign($assertion, $key, $sigAlg, $digestAlg);
        }
        if (in_array($sign, ['response', 'both'], true)) {
            $this->sign($root, $key, $sigAlg, $digestAlg);
        }
        if (isset($o['encrypt_for'])) {
            $this->encryptAssertion($dom, $o['encrypt_for'], $o['encryption_alg'] ?? XMLSecurityKey::AES128_CBC, $o['key_transport'] ?? XMLSecurityKey::RSA_OAEP_MGF1P);
        }
        if (isset($o['mutate'])) {
            ($o['mutate'])($dom);
        }
        $out = $dom->saveXML();
        if (isset($o['raw'])) {
            $out = ($o['raw'])($out);
        }

        return $out;
    }

    public function sign(DOMElement $element, array $key, string $sigAlg = XMLSecurityKey::RSA_SHA256, string $digestAlg = XMLSecurityDSig::SHA256): void
    {
        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference($element, $digestAlg, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
        $private = new XMLSecurityKey($sigAlg, ['type' => 'private']);
        $private->loadKey($key['key'], false);
        $dsig->sign($private);
        $dsig->add509Cert($key['cert'], true);
        // after the Issuer, where the schema wants it
        $issuer = null;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Issuer') {
                $issuer = $child;
                break;
            }
        }
        $dsig->insertSignature($element, $issuer?->nextSibling);
    }

    private function encryptAssertion(DOMDocument $dom, string $spCertPem, string $dataAlg, string $transportAlg): void
    {
        $xp = $this->xpath($dom);
        $assertion = $xp->query('/samlp:Response/saml:Assertion')->item(0);
        $enc = new XMLSecEnc();
        $enc->setNode($assertion);
        $enc->type = XMLSecEnc::Element;
        $sym = new XMLSecurityKey($dataAlg);
        $sym->generateSessionKey();
        $rsa = new XMLSecurityKey($transportAlg, ['type' => 'public']);
        $rsa->loadKey($spCertPem, false, true);
        $enc->encryptKey($rsa, $sym);
        // replaces the assertion by its EncryptedData; the schema wants that inside an EncryptedAssertion
        $enc->encryptNode($sym);
        $data = $dom->getElementsByTagNameNS('http://www.w3.org/2001/04/xmlenc#', 'EncryptedData')->item(0);
        $wrapper = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:EncryptedAssertion');
        $data->parentNode->replaceChild($wrapper, $data);
        $wrapper->appendChild($data);
    }

    public function xpath(DOMDocument $dom): DOMXPath
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xp->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        return $xp;
    }

    /**
     * A LogoutRequest or LogoutResponse and how to deliver it.
     *
     * @param array{type?:string,destination?:?string,issuer?:string,name_id?:string,session_index?:?string,in_response_to?:string,issued?:int,not_on_or_after?:?int,status?:string,id?:string,key?:array{key:string,cert:string},sig_alg?:string,binding?:string,sign?:bool,relay_state?:?string} $o
     * @return array{query:string,form:array<string,string>} `query` is a raw query string (redirect binding), `form` the POST fields
     */
    public function logoutMessage(array $o): array
    {
        $type = $o['type'] ?? 'request';
        $now = time();
        $iso = fn (int $t) => gmdate('Y-m-d\TH:i:s\Z', $t);
        $id = $o['id'] ?? '_l' . bin2hex(random_bytes(8));
        $destination = array_key_exists('destination', $o) ? $o['destination'] : null;
        $issuer = $o['issuer'] ?? self::ENTITY_ID;
        $issued = $o['issued'] ?? $now;
        $attrs = 'ID="' . $id . '" Version="2.0" IssueInstant="' . $iso($issued) . '"' . ($destination !== null ? ' Destination="' . $destination . '"' : '');

        if ($type === 'request') {
            $notOnOrAfter = array_key_exists('not_on_or_after', $o) ? $o['not_on_or_after'] : $now + 300;
            $xml = '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ' . $attrs . ($notOnOrAfter !== null ? ' NotOnOrAfter="' . $iso($notOnOrAfter) . '"' : '') . '>'
                . '<saml:Issuer>' . $issuer . '</saml:Issuer><saml:NameID>' . ($o['name_id'] ?? 'user-1') . '</saml:NameID>'
                . (($o['session_index'] ?? null) !== null ? '<samlp:SessionIndex>' . $o['session_index'] . '</samlp:SessionIndex>' : '')
                . '</samlp:LogoutRequest>';
            $param = 'SAMLRequest';
        } else {
            $xml = '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ' . $attrs . ' InResponseTo="' . ($o['in_response_to'] ?? '_none') . '">'
                . '<saml:Issuer>' . $issuer . '</saml:Issuer><samlp:Status><samlp:StatusCode Value="' . ($o['status'] ?? 'urn:oasis:names:tc:SAML:2.0:status:Success') . '"/></samlp:Status></samlp:LogoutResponse>';
            $param = 'SAMLResponse';
        }

        $key = $o['key'] ?? $this->signing;
        $sigAlg = $o['sig_alg'] ?? XMLSecurityKey::RSA_SHA256;
        $sign = $o['sign'] ?? true;
        $relay = $o['relay_state'] ?? null;

        if (($o['binding'] ?? 'redirect') === 'post') {
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            if ($sign) {
                $this->sign($dom->documentElement, $key, $sigAlg);
            }
            if (isset($o['mutate'])) {
                ($o['mutate'])($dom);
            }

            return ['query' => '', 'form' => [$param => base64_encode($dom->saveXML())] + ($relay !== null ? ['RelayState' => $relay] : [])];
        }

        $encoded = base64_encode(gzdeflate($xml));
        $query = $param . '=' . urlencode($encoded);
        if ($relay !== null) {
            $query .= '&RelayState=' . urlencode($relay);
        }
        if ($sign) {
            $query .= '&SigAlg=' . urlencode($sigAlg);
            $private = new XMLSecurityKey($sigAlg, ['type' => 'private']);
            $private->loadKey($key['key'], false);
            $query .= '&Signature=' . urlencode(base64_encode($private->signData($query)));
        }

        return ['query' => $query, 'form' => []];
    }
}
