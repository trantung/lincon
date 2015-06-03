<?php

class BaseController extends Controller {

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

	protected function json($data, $jsonp = false) {
		$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($jsonp) {
			$data = 'OyaOffice.searchResult('. $data . ');';
		}
		$response = Response::make($data, 200);
		$response->header('Content-Type', 'application/json');
		return $response;
	}

}
