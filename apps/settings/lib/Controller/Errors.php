<?php


namespace OCA\Settings\Controller;

use OCA\Settings\Service\NotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait Errors {
	protected function handleNotFound(\Closure $callback) {
		try {
			return new DataResponse($callback());
		} catch (NotFoundException $e) {
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
