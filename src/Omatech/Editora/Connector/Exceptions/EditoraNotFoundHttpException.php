<?php
namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditoraNotFoundHttpException extends NotFoundHttpException
{
    public function render()
    {
        $viewData = [];

        return response()->view('editora.404', $viewData, 404);
    }
}