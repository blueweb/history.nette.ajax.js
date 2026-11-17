<?php

namespace Blueweb\NetteAjax;

use Nette\Application\Application;
use Nette\Application\Request as AppRequest;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\IRouter;
use Nette\Http;


/**
 * Automatically adds 'redirect' to payload when forward happens
 * to simplify userland code in presenters.
 *
 * Also bypasses 'redirect()' calls with 'forward()' calls.
 *
 * Sets 'Vary: X-Requested-With' header to disable payload caching.
 *
 * @author Vojtěch Dobeš
 */
class OnResponseHandler
{

	/** @var Http\IRequest */
	private $httpRequest;

	/** @var IRouter */
	private $router;

	/** @var bool */
	private $forwardHasHappened = FALSE;

	/** @var string */
	private $fragment = '';



	/**
	 * @param  Http\IRequest
	 * @param  IRouter
	 */
	public function __construct(Http\IRequest $httpRequest, IRouter $router)
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
				$url = new Http\UrlScript($payload->redirect);
				$url->setScriptPath($this->httpRequest->url->scriptPath);
				$httpRequest = new Http\Request(
					$url,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					$this->httpRequest->getMethod(),
					$this->httpRequest->getRemoteAddress(),
					$this->httpRequest->getRemoteHost()
				);

				if ($this->router->match($httpRequest) !== NULL) {
					$prop = new \ReflectionProperty('Nette\Application\Application', 'httpRequest');
					$prop->setAccessible(TRUE);
					$prop->setValue($application, $httpRequest);

					$application->run();
					exit;
				}
			} elseif ($this->forwardHasHappened && !isset($payload->redirect)) {
				// Error presenters have no URL to store; their parameters may expose exceptions or requests.
				if (!$this->isErrorForward($application)) {
					$presenter = $application->getPresenter();
					$payload->redirect = $presenter->link('this', self::filterLinkParameters($presenter->getParameters())) . $this->fragment;
				}
				$this->fragment = '';
			}
		}
	}



	/**
	 * @param  Application
	 * @return bool
	 */
	private function isErrorForward(Application $application)
	{
		$requests = $application->getRequests();
		$request = end($requests);
		if (!$request instanceof AppRequest) {
			return FALSE;
		}

		if (is_object($request->getParameter('exception'))) {
			return TRUE;
		}

		return $application->errorPresenter !== NULL
			&& strcasecmp($request->getPresenterName(), $application->errorPresenter) === 0;
	}



	/**
	 * @param  array
	 * @return array
	 */
	private static function filterLinkParameters(array $params)
	{
		foreach ($params as $key => $value) {
			if (is_array($value)) {
				$params[$key] = self::filterLinkParameters($value);
			} elseif ($value !== NULL && !is_scalar($value)) {
				// Presenter::link() restores omitted action arguments from the current request.
				$params[$key] = NULL;
			}
		}

		return $params;
	}

}
