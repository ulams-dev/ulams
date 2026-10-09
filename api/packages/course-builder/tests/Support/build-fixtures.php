<?php

/*
 * Rebuilds the PDF and DOCX golden fixtures from the Markdown ones (run once; the output is
 * committed): php packages/course-builder/tests/Support/build-fixtures.php
 */

use Ulams\CourseBuilder\Tests\Support\DocumentFixtures;

require __DIR__ . '/../../../../vendor/autoload.php';

$dir = __DIR__ . '/../../resources/fixtures';

// PDF: the coffee handbook with numbered headings, wrapped to ~90 characters, ~40 lines per page
$lines = [];
$section = 0;
$sub = 0;
foreach (preg_split('/\R/', (string) file_get_contents("{$dir}/coffee-brewing.md")) as $line) {
    if (preg_match('/^## (.+)/', $line, $m)) {
        $section++;
        $sub = 0;
        $lines[] = '';
        $lines[] = "{$section} {$m[1]}";
        continue;
    }
    if (preg_match('/^### (.+)/', $line, $m)) {
        $sub++;
        $lines[] = '';
        $lines[] = "{$section}.{$sub} " . ucwords($m[1]);
        continue;
    }
    if (preg_match('/^# /', $line)) {
        continue;
    }
    if (trim($line) === '') {
        $lines[] = '';
        continue;
    }
    foreach (explode("\n", wordwrap($line, 90)) as $wrapped) {
        $lines[] = str_replace(['°', '×', '÷'], ['deg', 'x', '/'], $wrapped);
    }
}
DocumentFixtures::pdf("{$dir}/coffee-handbook.pdf", array_chunk($lines, 42), 'Coffee Brewing Fundamentals');

// DOCX: git basics with headings, lists, code and a table
$paragraphs = [];
$inCode = false;
foreach (preg_split('/\R/', (string) file_get_contents("{$dir}/git-basics.md")) as $line) {
    if (str_starts_with($line, '```')) {
        $inCode = !$inCode;
        continue;
    }
    if ($inCode) {
        $paragraphs[] = ['code', $line];
    } elseif (preg_match('/^(#{1,3}) (.+)/', $line, $m)) {
        $paragraphs[] = ['h' . strlen($m[1]), $m[2]];
    } elseif (trim($line) !== '') {
        $paragraphs[] = ['p', str_replace('`', '', $line)];
    }
}
$paragraphs[] = ['h2', 'Command summary'];
$paragraphs[] = ['table', 'Command|What it does;git init|Creates a repository;git add|Stages files;git commit|Records a snapshot;git merge|Joins a branch into the current one'];
DocumentFixtures::docx("{$dir}/git-basics.docx", $paragraphs, 'Git Basics for Course Authors');

echo "ok\n";
