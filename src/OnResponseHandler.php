<?php

namespace Blueweb\NetteAjax;

use Nette\Application\Responses\JsonResponse;
use Nette\Http\IRequest;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Routing\Router;

/**
 * Automatically adds 'redirect' to payload when forward happens
 * to simplify userland code in presenters.
 * Also bypasses 'redirect()' calls with 'forward()' calls.
 * Sets 'Vary: X-Requested-With' header to disable payload caching.
 */
class OnResponseHandler
{
	/** @var IRequest */
	private $httpRequest;

	/** @var Router */
	private $router;

	/** @var bool */
	private $forwardHasHappened = FALSE;

	/** @var string */
	private $fragment = '';

	/**
	 * @param IRequest
	 * @param Router
	 */
	public function __construct(IRequest $httpRequest, Router $router)
	{
		$this->httpRequest = $httpRequest;
		$this->router = $router;
	}

	/**
	 * Stores information about ocurring forward() call
	 */
	public function markForward()
	{
		$this->forwardHasHappened = TRUE;
	}

	public function __invoke($application, $response)
	{
		if ($response instanceof JsonResponse && ($payload = $response->getPayload()) instanceof \stdClass) {
			if (!$this->forwardHasHappened && isset($payload->redirect)) {
				if (($fragmentPos = strpos($payload->redirect, '#')) !== FALSE) {
					$this->fragment = substr($payload->redirect, $fragmentPos);
				}
				$url = new UrlScript(
					$payload->redirect,
					$this->httpRequest->url->scriptPath
				);
				$httpRequest = new Request($url);

				if ($this->router->match($httpRequest) !== NULL) {
					$prop = new \ReflectionProperty(
						get_class($application),
						'httpRequest'
					);
					$prop->setAccessible(TRUE);
					$prop->setValue($application, $httpRequest);

					$application->run();
					exit;
				}
			} elseif ($this->forwardHasHappened && !isset($payload->redirect)) {
				$payload->redirect = $application->getPresenter()
						->link('this', $application->getPresenter()
							->getParameters()) . $this->fragment;
				$this->fragment = '';
			}
		}
	}
}
