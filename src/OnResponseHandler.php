<?php

namespace Blueweb\NetteAjax;

use Nette\Application\Application;
use Nette\Application\IResponse;
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
	private $forwardHasHappened = false;

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
		$this->forwardHasHappened = true;
	}

	/**
	 * @param Application $application
	 * @param IResponse $response
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function __invoke($application, $response)
	{
		if ($response instanceof JsonResponse && ($payload = $response->getPayload()) instanceof \stdClass) {
			if (!$this->forwardHasHappened && isset($payload->redirect)) {
				if (($fragmentPos = strpos($payload->redirect, '#')) !== false) {
					$this->fragment = substr($payload->redirect, $fragmentPos);
				}
				$url = new UrlScript(
					$payload->redirect,
					$this->httpRequest->url->scriptPath
				);

				$prop = new \ReflectionProperty(
					get_class($application),
					'httpRequest'
				);
				$prop->setAccessible(true);
				/** @var IRequest $originalRequest */
				$originalRequest = $prop->getValue($application);

				$httpRequest = new Request(
					$url,
					null,
					null,
					null,
					null,
					null,
					$originalRequest->getRemoteAddress(),
					$originalRequest->getRemoteHost()
				);

				if ($this->router->match($httpRequest) !== null) {
					$prop->setValue($application, $httpRequest);

					$application->run();
					exit;
				}
			} elseif ($this->forwardHasHappened && !isset($payload->redirect)) {
				// Forward na chybovy presenter nema vlastnu URL, ktoru by malo zmysel ulozit do historie.
				// Jeho parametre (exception, previousPresenter, request) su objekty a router by ich verejne
				// vlastnosti rozbalil do query stringu, cim by klientovi vyzradil interny stav aplikacie.
				if (!$this->isErrorForward($application)) {
					$presenter = $application->getPresenter();
					$payload->redirect = $presenter->link(
						'this',
						self::filterLinkParameters($presenter->getParameters())
					) . $this->fragment;
				}
				$this->fragment = '';
			}
		}
	}

	/**
	 * Detects forward to the error presenter made by Application::processException()
	 *
	 * @param Application $application
	 *
	 * @return bool
	 */
	private function isErrorForward($application): bool
	{
		$requests = $application->getRequests();
		$request = end($requests);
		if (!$request instanceof \Nette\Application\Request) {
			return false;
		}

		if ($request->getParameter('exception') instanceof \Throwable) {
			return true;
		}

		// error4xxPresenter existuje az od nette/application 3.2
		$errorPresenters = [
			$application->errorPresenter,
			property_exists($application, 'error4xxPresenter') ? $application->error4xxPresenter : null,
		];
		$presenterName = $request->getPresenterName();
		foreach ($errorPresenters as $errorPresenter) {
			if ($errorPresenter !== null && strcasecmp($presenterName, $errorPresenter) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keeps only scalar parameters (recursively in arrays), objects must never reach the URL
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	private static function filterLinkParameters(array $params): array
	{
		foreach ($params as $key => $value) {
			if (is_array($value)) {
				$params[$key] = self::filterLinkParameters($value);
			} elseif ($value !== null && !is_scalar($value)) {
				unset($params[$key]);
			}
		}

		return $params;
	}
}
