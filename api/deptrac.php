<?php


$paths = glob("./packages/*");

$a = [
    'deptrac' => [
        'paths' => [
            './packages'
        ],
        'layers' => []
    ]
];

foreach ($paths as $path) {
    if (strpos($path, "core")) {
        continue;
    }
    $a['deptrac']['layers'][] = [
        'name' => basename($path),
        'collectors' => [[
            'type' => 'directory',
            'value' => $path
        ]]

    ];
}

yaml_emit_file('deptrac-modules.yaml', $a);
