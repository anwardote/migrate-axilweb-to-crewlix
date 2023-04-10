


<?php


use \Illuminate\Support\Facades\Route;
use Anwardote\AxilwebToCrewlix\Http\Controllers\ExportController;

Route::get('/export', [ExportController::class, 'index']);