<?php

namespace App\Http\Controllers\Editora;

use App;

use Illuminate\Http\Request;

class Error404 extends GlobalController {

	public $inst_id;
	public $preview;
	public $viewData;

	public function render(Request $request) {
		$this->viewData['global'] = $global = $request['global'];
		$instance['id'] = 0;
		$instance['link'] = $request->getRequestUri();
		$this->viewData['instance'] = $instance;

		return response()->view('errors.404', $this->viewData, 404);
	}

}
