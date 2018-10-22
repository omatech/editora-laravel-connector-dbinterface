<?php

namespace App\Http\Controllers\Editora;

use App;

use Illuminate\Http\Request;
use Omatech\Editora\Generator\Generator;
use Omatech\Editora\Clear\Clear;

class Error404 extends GlobalController {

	public $inst_id;
	public $preview;
	public $viewData;

	public function render(Request $request) {
		$this->viewData['global'] = $global = $request['global'];
		$instance['id'] = 0;
		$instance['link'] = $request->getRequestUri();
		$this->viewData['instance'] = $instance;

		return response()->view('editora.404', $this->viewData, 404);
	}

}
