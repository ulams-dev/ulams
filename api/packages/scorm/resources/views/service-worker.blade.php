@php

// package root (packages/scorm), independent of where the package is installed
$scormRoot = dirname((new \ReflectionClass(\EscolaLms\Scorm\EscolaLmsScormServiceProvider::class))->getFileName(), 2);

// self.importScripts("modules/jszip.js");
// self.importScripts("modules/mimetypes.js");
// self.importScripts("modules/txml.js");

echo file_get_contents ($scormRoot.'/resources/js/modules/jszip.js');
echo file_get_contents ($scormRoot.'/resources/js/modules/mimetypes.js');
echo file_get_contents ($scormRoot.'/resources/js/modules/txml.js');
echo file_get_contents ($scormRoot.'/resources/js/serviceworker.js');
@endphp