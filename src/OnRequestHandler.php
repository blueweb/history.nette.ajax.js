<?php

namespace Blueweb\NetteAjax;

use Nette\Http\IRequest;

/**
 * Listens for forward calls
 */
class OnRequestHandler
{
	/** @var IRequest */
	private $httpRequest;

	/** @var OnResponseHandler */
	private $onResponseHandler;

	/**
	 * @param IRequest
	 * @param OnResponseHandler
	 */
	public function __construct(IRequest $httpRequest, OnResponseHandler $onResponseHandler)
	{
		$this->httpRequest = $httpRequest;
		$this->onResponseHandler = $onResponseHandler;
	}

	public function __invoke($application, $request)
	{
		if ($this->httpRequest->isAjax() && count($application->getRequests()) > 1) {
			$this->onResponseHandler->markForward();
		}
	}
}
