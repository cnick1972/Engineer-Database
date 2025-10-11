<?php
/**
 * Maintenance Log Application
 *
 * Copyright (c) 2024 The Maintenance Log Developers.
 * All rights reserved.
 *
 * This source code is proprietary and confidential. Unauthorized copying,
 * modification, distribution, or disclosure is strictly prohibited without
 * prior written consent.
 */

declare(strict_types=1);

$root = getcwd();
$bodyLines = [
    'Maintenance Log Application',
    '',
    'Copyright (c) 2024 The Maintenance Log Developers.',
    'All rights reserved.',
    '',
    'This source code is proprietary and confidential. Unauthorized copying,',
    'modification, distribution, or disclosure is strictly prohibited without',
    'prior written consent.',
];

function renderHeader(array $lines, string $style): string
{
    switch ($style) {
        case 'php':
            $out = "/**\n";
            foreach ($lines as $line) {
                $out .= $line === '' ? " *\n" : " * {$line}\n";
            }
            $out .= ' */';
            return $out;
        case 'html':
            $out = "<!--\n";
            foreach ($lines as $line) {
                $out .= $line === '' ? "  \n" : "  {$line}\n";
            }
            $out .= '-->';
            return $out;
        case 'hash':
            $out = '';
            $total = count($lines);
            foreach ($lines as $index => $line) {
                $prefix = '#';
                if ($line !== '') {
                    $prefix .= ' ';
                }
                $out .= $prefix . $line;
                if ($index < $total - 1) {
                    $out .= "\n";
                }
            }
            return $out;
        default:
            throw new RuntimeException('Unknown style: ' . $style);
    }
}

$skipSegments = ['vendor', 'storage', '.git', 'scripts'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$updated = [];

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relativePath = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

    $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
    if (array_intersect($parts, $skipSegments)) {
        continue;
    }

    $extension = strtolower($fileInfo->getExtension());
    $style = null;
    if ($extension === 'php') {
        $style = 'php';
    } elseif ($extension === 'html') {
        $style = 'html';
    } elseif ($extension === 'txt') {
        $style = 'hash';
    } elseif ($fileInfo->getBasename() === '.htaccess') {
        $style = 'hash';
    } else {
        continue;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Failed to read {$relativePath}\n");
        continue;
    }

    if (str_contains($contents, 'Maintenance Log Application')) {
        continue;
    }

    $header = renderHeader($bodyLines, $style);

    switch ($style) {
        case 'php':
            if (str_starts_with($contents, '<?php')) {
                $newContents = preg_replace(
                    '/^<\?php\s*/',
                    "<?php\n{$header}\n\n",
                    $contents,
                    1
                );
                if ($newContents === null) {
                    fwrite(STDERR, "Failed to update {$relativePath}\n");
                    continue 2;
                }
            } else {
                $trimmed = ltrim($contents, "\r\n");
                $newContents = "<?php\n{$header}\n?>\n" . $trimmed;
            }
            break;
        case 'html':
        case 'hash':
            $trimmed = ltrim($contents, "\r\n");
            $newContents = $header . "\n\n" . $trimmed;
            break;
        default:
            continue 2;
    }

    $normalized = rtrim($newContents, "\r\n") . "\n";

    if ($normalized === $contents) {
        continue;
    }

    if (file_put_contents($path, $normalized) === false) {
        fwrite(STDERR, "Failed to write {$relativePath}\n");
        continue;
    }

    $updated[] = $relativePath;
}

if ($updated === []) {
    echo "No files updated.\n";
    exit(0);
}

echo "Updated " . count($updated) . " file(s):\n";
foreach ($updated as $file) {
    echo " - {$file}\n";
}
