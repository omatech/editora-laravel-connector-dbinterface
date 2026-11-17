<?php

namespace Tests\Fixtures;

use Illuminate\Http\Request;

final class PreviewPage
{
    public $inst_id;
    public $nice_url;
    public $utils;
    public bool $preview = false;
    public array $viewData = [];

    public function render(Request $request)
    {
        return response()->json(['preview' => $this->preview, 'id' => $this->inst_id]);
    }
}
