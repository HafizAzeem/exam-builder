<?php

use App\Http\Controllers\Admin\InstituteProfileController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\PaperController;
use App\Http\Controllers\LayoutEditor\LayoutEditorController;
use App\Http\Controllers\PaperBuilder\QuestionSelectorController;
use App\Http\Controllers\PaperBuilder\WizardController;
use App\Http\Controllers\PastPaperController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'tenant', 'check.licence', 'teacher.scope'])->group(function () {
    Route::get('/dashboard', [PaperController::class, 'index'])->name('dashboard');

    Route::get('/builder', [WizardController::class, 'index'])->name('builder');
    Route::post('/builder', [WizardController::class, 'store'])->name('builder.store');

    Route::prefix('api/builder')->group(function () {
        Route::get('/grades/{grade}/subjects', [QuestionSelectorController::class, 'subjects']);
        Route::get('/subjects/{subject}/chapters', [QuestionSelectorController::class, 'chapters']);
        Route::get('/questions', [QuestionSelectorController::class, 'questions']);
        Route::post('/questions/random', [QuestionSelectorController::class, 'random']);
    });

    Route::get('/editor/{paper}', [LayoutEditorController::class, 'show'])->name('editor.show');
    Route::patch('/editor/{paper}', [LayoutEditorController::class, 'update'])->name('editor.update');
    Route::get('/editor/{paper}/print', [LayoutEditorController::class, 'print'])->name('editor.print');

    Route::delete('/papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');
    Route::post('/papers/{paper}/duplicate', [PaperController::class, 'duplicate'])->name('papers.duplicate');

    Route::get('/past-papers', [PastPaperController::class, 'index'])->name('past-papers.index');
    Route::get('/past-papers/{question}/print', [PastPaperController::class, 'quickPrint'])->name('past-papers.print');

    Route::middleware('role:institution_admin')->prefix('admin')->group(function () {
        Route::get('/teachers', [TeacherController::class, 'index'])->name('admin.teachers');
        Route::post('/teachers', [TeacherController::class, 'store'])->name('admin.teachers.store');
        Route::get('/profile', [InstituteProfileController::class, 'edit'])->name('admin.profile');
        Route::post('/profile', [InstituteProfileController::class, 'update'])->name('admin.profile.update');
        Route::get('/logs', [ActivityLogController::class, 'index'])->name('admin.logs');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
