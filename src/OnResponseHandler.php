<?php

namespace Blueweb\NetteAjax;

use Nette\Application\Application;
use Nette\Application\IResponse;
use Nette\Application\Request as AppRequest;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\IRequest;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Nette\Utils\Arrays;

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

				$httpRequest = new Request(
					$url,
					[],
					[],
					[],
					[],
					$this->httpRequest->getMethod(),
					$this->httpRequest->getRemoteAddress(),
					$this->httpRequest->getRemoteHost()
				);

				// Application::$httpRequest je v novom Nette readonly, preto namiesto reflexie
				// a opätovného run() zmatchujeme route ručne a spustíme cieľovú URL cez verejné processRequest().
				$params = $this->router->match($httpRequest);
				$presenter = $params[Presenter::PresenterKey] ?? null;
				if ($params !== null && is_string($presenter)) {
					unset($params[Presenter::PresenterKey]);
					$appRequest = new AppRequest(
						$presenter,
						$httpRequest->getMethod(),
						$params,
						$httpRequest->getPost(),
						$httpRequest->getFiles()
					);

					// Re-run obalíme rovnakým životným cyklom ako Application::run() (onStartup/onShutdown,
					// resp. onError pri výnimke), aby sa správal konzistentne s bežným requestom aj keď
					// neskôr pribudne logika na Application::onStartup. Pôvodne to zabezpečoval $application->run().
					Arrays::invoke($application->onStartup, $application);
					try {
						$application->processRequest($appRequest);
						Arrays::invoke($application->onShutdown, $application);
					} catch (\Throwable $e) {
						Arrays::invoke($application->onError, $application, $e);
						Arrays::invoke($application->onShutdown, $application, $e);
						throw $e;
					}
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
	private function isErrorForward(Application $application): bool
	{
		$requests = $application->getRequests();
		$request = end($requests);
		if (!$request instanceof AppRequest) {
			return false;
		}

		if ($request->getParameter('exception') instanceof \Throwable) {
			return true;
		}

		$presenterName = $request->getPresenterName();
		foreach ([$application->errorPresenter, $application->error4xxPresenter] as $errorPresenter) {
			if ($errorPresenter !== null && strcasecmp($presenterName, $errorPresenter) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keeps only scalar parameters (recursively in arrays), objects must never reach the URL
	 *
	 * @param array<mixed> $params
	 *
	 * @return array<mixed>
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
