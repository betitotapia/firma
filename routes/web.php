<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PublicReviewController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return redirect()->route('login.form');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DocumentController::class, 'index'])->name('dashboard');

    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::post('/documents/{document}/send', [DocumentController::class, 'sendLink'])
        ->name('documents.send')
        ->middleware('canperm:send');

    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy')
        ->middleware('canperm:delete');

    Route::get('/documents/{document}/download/{version}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/evidence.json', [DocumentController::class, 'evidenceJson'])->name('documents.evidence.json');
});


// Public review routes
Route::get('/r/{token}', [PublicReviewController::class, 'show'])->name('public.review');
Route::post('/r/{token}/download', [PublicReviewController::class, 'download'])->name('public.download');
Route::post('/r/{token}/request-otp', [PublicReviewController::class, 'requestOtp'])->name('public.requestOtp');
Route::post('/r/{token}/verify-otp', [PublicReviewController::class, 'verifyOtp'])->name('public.verifyOtp');
Route::post('/r/{token}/upload-signed', [PublicReviewController::class, 'uploadSigned'])->name('public.uploadSigned');
Route::post('/r/{token}/sign-in-app', [PublicReviewController::class, 'signInApp'])->name('public.signInApp');
Route::post('r/{token}/download-signed/{type}', [PublicReviewController::class, 'downloadSigned'])->whereIn('type', ['employee', 'final'])->name('public.downloadSigned');
Route::get('evidence/{evidenceId}', [DocumentController::class, 'verifyEvidence'])->name('evidence.verify');
Route::post('r/{token}/liveness/challenge', [PublicReviewController::class, 'livenessChallenge'])->name('public.liveness.challenge');
Route::post('r/{token}/liveness/upload', [PublicReviewController::class, 'uploadLiveness'])->name('public.liveness.upload');
Route::get('e/{evidenceId}', [\App\Http\Controllers\EvidenceController::class, 'show'])->name('evidence.public.show');