<?php

/**
 * Regenerates the small SCORM 1.2 sample packages used by the seeder and the tests:
 *   php packages/scorm/database/mocks/make-mocks.php
 * 1.zip and 3.zip used to be 24 MB and 6.8 MB third-party courses; these have the same titles, one
 * SCO each, and are a few kilobytes. 2.zip and the RuntimeBasicCalls_* packages are unchanged.
 */

$packages = [
    '1.zip' => ['id' => 'employee-health-and-wellness', 'title' => 'Employee Health and Wellness (Sample Course)'],
    '3.zip' => ['id' => 'sl360-lms-scorm-12', 'title' => 'SL360 LMS SCORM 1.2'],
];

foreach ($packages as $file => $package) {
    $title = htmlspecialchars($package['title'], ENT_XML1);
    $manifest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="{$package['id']}" version="1"
  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
                      http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="org1">
    <organization identifier="org1">
      <title>{$title}</title>
      <item identifier="i1" identifierref="r1" isvisible="true">
        <title>{$title}</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="r1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html" />
    </resource>
  </resources>
</manifest>
XML;

    $page = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$title}</title></head>
<body>
<h1>{$title}</h1>
<p>Sample SCORM 1.2 content.</p>
<script>
  var api = null;
  for (var w = window, n = 0; w && !api && n < 10; w = w.parent, n++) { api = w.API || null; if (w === w.parent) break; }
  if (api) {
    api.LMSInitialize('');
    api.LMSSetValue('cmi.core.lesson_status', 'completed');
    api.LMSCommit('');
    api.LMSFinish('');
  }
</script>
</body>
</html>
HTML;

    $path = __DIR__ . '/' . $file;
    @unlink($path);
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('imsmanifest.xml', $manifest);
    $zip->addFromString('index.html', $page);
    $zip->close();
    echo $file . ': ' . filesize($path) . " bytes\n";
}
