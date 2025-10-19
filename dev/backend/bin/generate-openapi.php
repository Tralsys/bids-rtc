#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// スキャン対象ディレクトリ
$srcDir = __DIR__ . '/../src';

// OpenAPI定義を生成
$openapi = \OpenApi\Generator::scan([$srcDir]);

// YAML形式で出力
$yamlOutput = $openapi->toYaml();

// 出力先ディレクトリを作成
$outputDir = __DIR__ . '/../docs';
if (!is_dir($outputDir)) {
  mkdir($outputDir, 0755, true);
}

// ファイルに書き込み
$outputFile = $outputDir . '/openapi.yaml';
file_put_contents($outputFile, $yamlOutput);

echo "OpenAPI specification generated successfully!\n";
echo "Output: $outputFile\n";
