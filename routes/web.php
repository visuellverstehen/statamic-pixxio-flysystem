<?php

use Illuminate\Support\Facades\Route;

/*
 * To prevent image validation from failing, we provide a route for all images redirecting to the absolute image url.
 */
Route::get('/pixxio-file/{id}/file.{extension}', function ($id, $extension) {

    $file = \VV\PixxioFlysystem\Models\PixxioFile::query()->where('pixxio_id', $id)->first();

    if (!$file) {
        abort(404);
    }

    return redirect($file->absolute_path);
});