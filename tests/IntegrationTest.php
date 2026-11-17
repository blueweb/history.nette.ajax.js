<?php

// Run with NETTE_VENDOR_AUTOLOAD=/path/to/nette-2.4/vendor/autoload.php php tests/IntegrationTest.php
$autoload = getenv('NETTE_VENDOR_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
	fwrite(STDERR, "Set NETTE_VENDOR_AUTOLOAD to a Nette 2.4 vendor/autoload.php file.\n");
	exit(1);
}

function runScenario($scenario)
{
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/IntegrationScenario.php') . ' ' . escapeshellarg($scenario) . ' 2>&1';
	exec($command, $output, $exitCode);
	$output = implode("\n", $output);
	if ($exitCode !== 0) {
		throw new Exception("Scenario $scenario failed: $output");
	}

	$payloads = array();
	foreach (preg_split('/(?<=\})(?=\{)/', $output) as $json) {
		$payload = json_decode($json, TRUE);
		if (!is_array($payload)) {
			throw new Exception("Scenario $scenario returned invalid JSON: $output");
		}
		$payloads[] = $payload;
	}

	return array($payloads, $output);
}

function check($condition, $message)
{
	if (!$condition) {
		throw new Exception($message);
	}
}

list($payloads) = runScenario('redirect');
$article = end($payloads);
$rerunRequest = end($article['matchedRequests']);
check($article['presenter'] === 'Article', 'Redirect did not rerun the target presenter');
check($rerunRequest === array('method' => 'POST', 'remoteAddress' => '192.0.2.5', 'remoteHost' => 'client.test'), 'Redirect rerun lost request metadata');

list($payloads, $output) = runScenario('forward');
check(count($payloads) === 1 && $payloads[0]['presenter'] === 'Article', 'Forward did not return target payload');
check(strpos($payloads[0]['redirect'], 'presenter=Article') !== FALSE, 'Forward redirect URL was not added');
check(strpos($payloads[0]['redirect'], 'id=7') !== FALSE, 'Forward lost scalar parameter');
check(strpos($output, 'SYNTHETIC_SECRET') === FALSE && strpos($payloads[0]['redirect'], 'unsafe') === FALSE, 'Object parameter leaked into redirect URL');

list($payloads, $output) = runScenario('error');
check(count($payloads) === 1 && $payloads[0]['presenter'] === 'Error', 'Error forward did not reach error presenter');
check(!isset($payloads[0]['redirect']), 'Error presenter URL was exposed');
check(strpos($output, 'Synthetic failure') === FALSE, 'Exception data leaked into payload');

echo "Nette 2.4 integration checks passed.\n";
