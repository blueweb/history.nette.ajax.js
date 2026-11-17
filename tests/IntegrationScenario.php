<?php

// Child process for IntegrationTest.php; a redirect rerun ends the process with exit.
$autoload = getenv('NETTE_VENDOR_AUTOLOAD');
require $autoload;
require __DIR__ . '/../src/OnRequestHandler.php';
require __DIR__ . '/../src/OnResponseHandler.php';

use Nette\Application;
use Nette\Http;

class RecordingRouter implements Application\IRouter
{
	private $router;

	public function __construct()
	{
		$this->router = new Application\Routers\SimpleRouter;
	}

	public function match(Http\IRequest $httpRequest)
	{
		$GLOBALS['matchedRequests'][] = array(
			'method' => $httpRequest->getMethod(),
			'remoteAddress' => $httpRequest->getRemoteAddress(),
			'remoteHost' => $httpRequest->getRemoteHost(),
		);
		return $this->router->match($httpRequest);
	}

	public function constructUrl(Application\Request $appRequest, Http\Url $refUrl)
	{
		return $this->router->constructUrl($appRequest, $refUrl);
	}
}

class BasePresenter extends Application\UI\Presenter
{
	public $autoCanonicalize = FALSE;

	protected function sendScenarioPayload()
	{
		$this->payload->presenter = $this->getName();
		$this->payload->matchedRequests = $GLOBALS['matchedRequests'];
		$this->sendPayload();
	}
}

class SecretValue
{
	public $privateData = 'SYNTHETIC_SECRET';
}

class HomepagePresenter extends BasePresenter
{
	public function actionDefault($scenario)
	{
		if ($scenario === 'redirect') {
			$this->redirect('Article:default', array('id' => 5));
		} elseif ($scenario === 'forward') {
			$this->forward('Article:default', array('id' => 7, 'unsafe' => new SecretValue));
		}
		throw new RuntimeException('Synthetic failure');
	}
}

class ArticlePresenter extends BasePresenter
{
	public function actionDefault($id, $unsafe = NULL)
	{
		$this->sendScenarioPayload();
	}
}

class ErrorPresenter extends BasePresenter
{
	public function actionDefault($exception)
	{
		$this->sendScenarioPayload();
	}
}

$scenario = $argv[1];
$GLOBALS['matchedRequests'] = array();
$url = new Http\UrlScript('https://example.test/index.php?presenter=Homepage&action=default&scenario=' . $scenario);
$url->setScriptPath('/index.php');
$httpRequest = new Http\Request(
	$url,
	NULL,
	NULL,
	NULL,
	NULL,
	array('X-Requested-With' => 'XMLHttpRequest'),
	'POST',
	'192.0.2.5',
	'client.test'
);
$httpResponse = new Http\Response;
$router = new RecordingRouter;
$presenterFactory = NULL;
$presenterFactory = new Application\PresenterFactory(function ($class) use (&$presenterFactory, $router, $httpRequest, $httpResponse) {
	$presenter = new $class;
	$presenter->injectPrimary(NULL, $presenterFactory, $router, $httpRequest, $httpResponse);
	return $presenter;
});
$application = new Application\Application($presenterFactory, $router, $httpRequest, $httpResponse);
$application->catchExceptions = TRUE;
$application->errorPresenter = 'Error';
$onResponseHandler = new Blueweb\NetteAjax\OnResponseHandler($httpRequest, $router);
$application->onRequest[] = new Blueweb\NetteAjax\OnRequestHandler($httpRequest, $onResponseHandler);
$application->onResponse[] = $onResponseHandler;
$application->run();
