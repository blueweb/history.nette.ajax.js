<?php

// Run with NETTE_VENDOR_AUTOLOAD=/path/to/vendor/autoload.php php tests/BackportTest.php
$autoload = getenv('NETTE_VENDOR_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
	fwrite(STDERR, "Set NETTE_VENDOR_AUTOLOAD to a Nette 2.4 vendor/autoload.php file.\n");
	exit(1);
}
require $autoload;
require __DIR__ . '/../src/OnResponseHandler.php';
require __DIR__ . '/../src/HistoryExtension.php';
check(is_subclass_of('Blueweb\\NetteAjax\\HistoryExtension', 'Nette\\DI\\CompilerExtension'), 'Nette 2 compiler extension is not available');

class TestRouter implements Nette\Application\IRouter
{
	public $request;
	public $matchResult = NULL;

	public function match(Nette\Http\IRequest $httpRequest)
	{
		$this->request = $httpRequest;
		return $this->matchResult; // Inspect the request before Application::run exits.
	}

	public function constructUrl(Nette\Application\Request $appRequest, Nette\Http\Url $refUrl)
	{
		return NULL;
	}
}

class TestPresenter
{
	public $params;

	public function link($destination, $params)
	{
		$this->params = $params;
		return '/target';
	}

	public function getParameters()
	{
		return array('id' => 42, 'nested' => array('ok' => 'yes', 'unsafe' => new stdClass), 'unsafe' => new stdClass);
	}
}

class TestApplication extends Nette\Application\Application
{
	public $requests = array();
	public $presenter;
	public $rerun = FALSE;

	public function run()
	{
		$this->rerun = TRUE;
		throw new Exception('Rerun reached');
	}

	public function __construct()
	{
	}

	public function getRequests()
	{
		return $this->requests;
	}

	public function getPresenter()
	{
		return $this->presenter;
	}
}

function check($condition, $message)
{
	if (!$condition) {
		throw new Exception($message);
	}
}

$source = new Nette\Http\Request(new Nette\Http\UrlScript('https://example.test/base/current'), NULL, NULL, NULL, NULL, NULL, 'POST', '192.0.2.5', 'client.test');
$router = new TestRouter;
$handler = new Blueweb\NetteAjax\OnResponseHandler($source, $router);
$app = new TestApplication;
$app->errorPresenter = 'Error';
$app->presenter = new TestPresenter;
$redirect = new stdClass;
$redirect->redirect = '/target#section';
$handler($app, new Nette\Application\Responses\JsonResponse($redirect));
check($router->request->getMethod() === 'POST', 'Redirect lost its HTTP method');
check($router->request->getRemoteAddress() === '192.0.2.5', 'Redirect lost remote address');
check($router->request->getRemoteHost() === 'client.test', 'Redirect lost remote host');

$router->matchResult = array('presenter' => 'Article');
try {
	$handler($app, new Nette\Application\Responses\JsonResponse($redirect));
	throw new Exception('Application was not rerun');
} catch (Exception $e) {
	check($e->getMessage() === 'Rerun reached', 'Unexpected rerun failure');
}
$prop = new ReflectionProperty('Nette\Application\Application', 'httpRequest');
$prop->setAccessible(TRUE);
check($app->rerun && $prop->getValue($app) === $router->request, 'Application did not receive the redirect request');

$handler->markForward();
$app->requests = array(new Nette\Application\Request('Article', 'GET'));
$forward = new stdClass;
$handler($app, new Nette\Application\Responses\JsonResponse($forward));
check($forward->redirect === '/target#section', 'Forward did not retain URL fragment');
check($app->presenter->params === array('id' => 42, 'nested' => array('ok' => 'yes', 'unsafe' => NULL), 'unsafe' => NULL), 'Objects were not suppressed in forwarded URL parameters');

$app->requests = array(new Nette\Application\Request('Error', 'GET', array('exception' => new Exception('private'))));
$error = new stdClass;
$handler($app, new Nette\Application\Responses\JsonResponse($error));
check(!isset($error->redirect), 'Error presenter URL was exposed');
$app->requests = array(new Nette\Application\Request('Error', 'GET'));
$namedError = new stdClass;
$handler($app, new Nette\Application\Responses\JsonResponse($namedError));
check(!isset($namedError->redirect), 'Named error presenter URL was exposed');

echo "Nette 2.4 backport checks passed.\n";
