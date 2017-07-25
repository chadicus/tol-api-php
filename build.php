#!/usr/bin/env php
<?php
chdir(__DIR__);

$returnStatus = null;
passthru('composer install', $returnStatus);
if ($returnStatus !== 0) {
    exit(1);
}

require 'vendor/autoload.php';

$phpcsCLI = new PHP_CodeSniffer_CLI();
$phpcsArguments = [
    'standard' => [__DIR__ . '/vendor/dominionenterprises/dws-coding-standard/DWS'],
    'files' => ['src', 'tests', 'build.php'],
    'warningSeverity' => 0,
];
$phpcsViolations = $phpcsCLI->process($phpcsArguments);
if ($phpcsViolations > 0) {
    exit(1);
}

$phpunitConfiguration = PHPUnit\Util\Configuration::getInstance(__DIR__ . '/phpunit.xml');
$phpunitArguments = ['coverageHtml' => __DIR__ . '/coverage', 'configuration' => $phpunitConfiguration];
$testRunner = new PHPUnit\TextUI\TestRunner();
$result = $testRunner->doRun($phpunitConfiguration->getTestSuiteConfiguration(), $phpunitArguments, false);
if (!$result->wasSuccessful()) {
    exit(1);
}

$cloverCoverage = new \SebastianBergmann\CodeCoverage\Report\Clover();
file_put_contents('clover.xml', $cloverCoverage->process($result->getCodeCoverage()));

$coverageBuilder = new \SebastianBergmann\CodeCoverage\Node\Builder();
$coverageReport = $coverageBuilder->build($result->getCodeCoverage());
if ($coverageReport->getNumExecutedLines() !== $coverageReport->getNumExecutableLines()) {
    file_put_contents('php://stderr', "Code coverage was NOT 100%\n");
    exit(1);
}

echo "Code coverage was 100%\n";
