<?php

use App\Http\Controllers\Admin\InstituteProfileController;
use App\Http\Controllers\Admin\QuestionBankController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LoginHistoryController;
use App\Http\Controllers\Dashboard\PaperController;
use App\Http\Controllers\Dashboard\PaperHistoryController;
use App\Http\Controllers\LayoutEditor\LayoutEditorController;
use App\Http\Controllers\PaperBuilder\QuestionSelectorController;
use App\Http\Controllers\PaperBuilder\WizardController;
use App\Http\Controllers\PastPaperController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AIImportController;
use App\Http\Controllers\SuperAdmin\ImportHistoryController;
use App\Http\Controllers\SuperAdmin\PastPaperCollectorController;
use App\Http\Controllers\SuperAdmin\ReviewController;
use App\Http\Controllers\SuperAdmin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'tenant', 'check.licence', 'teacher.scope'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/saved-papers', [PaperController::class, 'index'])->name('saved-papers.index');
    Route::get('/paper-history', [PaperHistoryController::class, 'index'])->name('paper-history.index');

    Route::get('/builder', [WizardController::class, 'classes'])->name('builder');
    Route::get('/builder/subjects', [WizardController::class, 'subjects'])->name('builder.subjects');
    Route::get('/builder/chapters', [WizardController::class, 'chapters'])->name('builder.chapters');
    Route::get('/builder/create', [WizardController::class, 'index'])->name('builder.create');
    Route::post('/builder', [WizardController::class, 'store'])->name('builder.store');

    Route::prefix('api/builder')->group(function () {
        Route::get('/grades/{grade}/subjects', [QuestionSelectorController::class, 'subjects']);
        Route::get('/subjects/{subject}/chapters', [QuestionSelectorController::class, 'chapters']);
        Route::get('/questions', [QuestionSelectorController::class, 'questions']);
        Route::get('/questions/all', [QuestionSelectorController::class, 'all']);
        Route::get('/past-paper-filters', [QuestionSelectorController::class, 'pastPaperFilters']);
        Route::match(['get', 'post'], '/questions/by-ids', [QuestionSelectorController::class, 'byIds']);
        Route::post('/questions/random', [QuestionSelectorController::class, 'random']);
    });

    Route::get('/editor/{paper}', [LayoutEditorController::class, 'show'])->name('editor.show');
    Route::patch('/editor/{paper}', [LayoutEditorController::class, 'update'])->name('editor.update');
    Route::post('/editor/{paper}/watermark-image', [LayoutEditorController::class, 'uploadWatermarkImage'])->name('editor.watermark-image');
    Route::get('/editor/{paper}/print', [LayoutEditorController::class, 'print'])->name('editor.print');
    Route::post('/editor/{paper}/pdf', [LayoutEditorController::class, 'pdf'])->name('editor.pdf');

    Route::get('/print/{paper}', [LayoutEditorController::class, 'signedPrint'])
        ->name('paper.print.signed')
        ->middleware('signed');

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
        Route::get('/login-history', [LoginHistoryController::class, 'index'])->name('admin.login-history');

        Route::get('/question-bank', [QuestionBankController::class, 'index'])->name('admin.question-bank.index');
        Route::post('/question-bank', [QuestionBankController::class, 'store'])->name('admin.question-bank.store');
        Route::patch('/question-bank/{question}', [QuestionBankController::class, 'update'])->name('admin.question-bank.update');
        Route::delete('/question-bank/{question}', [QuestionBankController::class, 'destroy'])->name('admin.question-bank.destroy');
        Route::post('/question-bank/bulk-delete', [QuestionBankController::class, 'bulkDestroy'])->name('admin.question-bank.bulkDestroy');
        Route::get('/question-bank/import', [QuestionBankController::class, 'importForm'])->name('admin.question-bank.importForm');
        Route::post('/question-bank/import', [QuestionBankController::class, 'import'])->name('admin.question-bank.import');
    });
});

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [AIImportController::class, 'dashboard'])->name('ai-import.dashboard');
    Route::get('/ai-import', [AIImportController::class, 'dashboard'])->name('ai-import.index');
    Route::get('/ai-import/upload', [AIImportController::class, 'create'])->name('ai-import.create');
    Route::post('/ai-import', [AIImportController::class, 'store'])->name('ai-import.store');
    Route::get('/ai-import/{import}', [AIImportController::class, 'show'])->name('ai-import.show');
    Route::get('/ai-import/{import}/status', [AIImportController::class, 'status'])->name('ai-import.status');
    Route::get('/ai-import/{import}/download', [AIImportController::class, 'download'])->name('ai-import.download');
    Route::delete('/ai-import/{import}', [AIImportController::class, 'destroy'])->name('ai-import.destroy');

    Route::get('/ai-import/{import}/review', [ReviewController::class, 'show'])->name('ai-import.review');
    Route::patch('/ai-import/{import}/questions/{question}', [ReviewController::class, 'update'])->name('ai-import.questions.update');
    Route::post('/ai-import/{import}/questions/{question}/approve', [ReviewController::class, 'approve'])->name('ai-import.questions.approve');
    Route::post('/ai-import/{import}/questions/{question}/reject', [ReviewController::class, 'reject'])->name('ai-import.questions.reject');
    Route::post('/ai-import/{import}/questions/{question}/merge', [ReviewController::class, 'merge'])->name('ai-import.questions.merge');
    Route::post('/ai-import/{import}/bulk', [ReviewController::class, 'bulk'])->name('ai-import.bulk');
    Route::post('/ai-import/{import}/import-approved', [ReviewController::class, 'importApproved'])->name('ai-import.import-approved');

    Route::get('/ai-import-history', [ImportHistoryController::class, 'index'])->name('ai-import.history');
    Route::get('/ai-settings', [SettingsController::class, 'edit'])->name('ai-import.settings');
    Route::put('/ai-settings', [SettingsController::class, 'update'])->name('ai-import.settings.update');

    Route::get('/past-paper-collector', [PastPaperCollectorController::class, 'create'])->name('past-paper-collector.create');
    Route::post('/past-paper-collector', [PastPaperCollectorController::class, 'store'])->name('past-paper-collector.store');
    Route::get('/past-paper-collector/history', [PastPaperCollectorController::class, 'index'])->name('past-paper-collector.index');
    Route::get('/past-paper-collector/{collection}', [PastPaperCollectorController::class, 'show'])->name('past-paper-collector.show');
    Route::get('/past-paper-collector/{collection}/status', [PastPaperCollectorController::class, 'status'])->name('past-paper-collector.status');
    Route::get('/past-paper-collector/{collection}/sources/{source}', [PastPaperCollectorController::class, 'source'])->name('past-paper-collector.source');
    Route::post('/past-paper-collector/{collection}/retry', [PastPaperCollectorController::class, 'retry'])->name('past-paper-collector.retry');
    Route::post('/past-paper-collector/{collection}/sources/{source}/retry', [PastPaperCollectorController::class, 'retrySource'])->name('past-paper-collector.source.retry');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
