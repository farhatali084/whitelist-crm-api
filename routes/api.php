<?php

use App\Http\Controllers\CallController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ManagerScheduleController;
use App\Http\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

// Paths exactly as in the assignment (no /api prefix, see bootstrap/app.php).
Route::post('/companies', [CompanyController::class, 'store']);
Route::get('/companies', [CompanyController::class, 'index']);

Route::post('/calls', [CallController::class, 'store']);

Route::post('/meetings', [MeetingController::class, 'store']);
Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
Route::patch('/meetings/{meeting}', [MeetingController::class, 'update']);

Route::get('/managers/{manager}/schedule', ManagerScheduleController::class);
