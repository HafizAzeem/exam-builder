<?php

use App\Http\Controllers\Admin\InstituteProfileController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LoginHistoryController;
use App\Http\Controllers\Dashboard\PaperController;
use App\Http\Controllers\Dashboard\PaperHistoryController;
use App\Http\Controllers\LayoutEditor\LayoutEditorController;
use App\Http\Controllers\PaperBuilder\QuestionSelectorController;
use App\Http\Controllers\PaperBuilder\TeacherAIController;
use App\Http\Controllers\PaperBuilder\WizardController;
use App\Http\Controllers\PastPaperController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AIImportController;
use App\Http\Controllers\SuperAdmin\Curriculum\BoardController;
use App\Http\Controllers\SuperAdmin\Curriculum\ChapterController;
use App\Http\Controllers\SuperAdmin\Curriculum\GradeController;
use App\Http\Controllers\SuperAdmin\Curriculum\SubjectController;
use App\Http\Controllers\SuperAdmin\Curriculum\TopicController;
use App\Http\Controllers\SuperAdmin\ImportHistoryController;
use App\Http\Controllers\SuperAdmin\PastPaperCollectorController;
use App\Http\Controllers\SuperAdmin\PreferredQuestionSiteController;
use App\Http\Controllers\SuperAdmin\QuestionBankController;
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

    Route::get('/builder/ai/paste', [TeacherAIController::class, 'pasteForm'])->name('builder.ai.paste');
    Route::post('/builder/ai/paste', [TeacherAIController::class, 'storePaste'])->name('builder.ai.paste.store');
    Route::get('/builder/ai/generate', [TeacherAIController::class, 'generateForm'])->name('builder.ai.generate');
    Route::post('/builder/ai/generate', [TeacherAIController::class, 'storeGenerate'])->name('builder.ai.generate.store');
    Route::get('/builder/ai/{import}/status', [TeacherAIController::class, 'status'])->name('builder.ai.status');
    Route::get('/builder/ai/{import}/status.json', [TeacherAIController::class, 'statusJson'])->name('builder.ai.status.json');
    Route::get('/builder/ai/{import}/review', [TeacherAIController::class, 'review'])->name('builder.ai.review');
    Route::get('/builder/ai/{import}/search-bank', [TeacherAIController::class, 'searchBank'])->name('builder.ai.search-bank');
    Route::post('/builder/ai/{import}/manual-question', [TeacherAIController::class, 'storeManualQuestion'])->name('builder.ai.manual-question');
    Route::post('/builder/ai/{import}/bank-questions', [TeacherAIController::class, 'addBankQuestionsToSelection'])->name('builder.ai.bank-questions');
    Route::post('/builder/ai/{import}/add-to-paper', [TeacherAIController::class, 'addToPaper'])->name('builder.ai.add-to-paper');

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
    });
});

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [AIImportController::class, 'dashboard'])->name('ai-import.dashboard');
    Route::get('/ai-import', [AIImportController::class, 'dashboard'])->name('ai-import.index');
    Route::get('/ai-import/upload', [AIImportController::class, 'create'])->name('ai-import.create');
    Route::post('/ai-import', [AIImportController::class, 'store'])->name('ai-import.store');
    Route::get('/ai-import/paste', [AIImportController::class, 'pasteForm'])->name('ai-import.paste');
    Route::post('/ai-import/paste', [AIImportController::class, 'storePaste'])->name('ai-import.paste.store');
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

    Route::get('/preferred-sites', [PreferredQuestionSiteController::class, 'index'])->name('preferred-sites.index');
    Route::post('/preferred-sites', [PreferredQuestionSiteController::class, 'store'])->name('preferred-sites.store');
    Route::patch('/preferred-sites/{preferredQuestionSite}', [PreferredQuestionSiteController::class, 'update'])->name('preferred-sites.update');
    Route::delete('/preferred-sites/{preferredQuestionSite}', [PreferredQuestionSiteController::class, 'destroy'])->name('preferred-sites.destroy');

    Route::prefix('curriculum')->name('curriculum.')->group(function () {
        Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
        Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
        Route::patch('/grades/{grade}', [GradeController::class, 'update'])->name('grades.update');
        Route::delete('/grades/{grade}', [GradeController::class, 'destroy'])->name('grades.destroy');

        Route::get('/grades/{grade}/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/grades/{grade}/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::get('/subjects/{subject}/chapters', [ChapterController::class, 'index'])->name('chapters.index');
        Route::post('/subjects/{subject}/chapters', [ChapterController::class, 'store'])->name('chapters.store');
        Route::patch('/chapters/{chapter}', [ChapterController::class, 'update'])->name('chapters.update');
        Route::delete('/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');

        Route::get('/chapters/{chapter}/topics', [TopicController::class, 'index'])->name('topics.index');
        Route::post('/chapters/{chapter}/topics', [TopicController::class, 'store'])->name('topics.store');
        Route::patch('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

        Route::get('/boards', [BoardController::class, 'index'])->name('boards.index');
        Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
        Route::patch('/boards/{board}', [BoardController::class, 'update'])->name('boards.update');
        Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
    });

    Route::get('/question-bank', [QuestionBankController::class, 'index'])->name('question-bank.index');
    Route::post('/question-bank', [QuestionBankController::class, 'store'])->name('question-bank.store');
    Route::patch('/question-bank/{question}', [QuestionBankController::class, 'update'])->name('question-bank.update');
    Route::delete('/question-bank/{question}', [QuestionBankController::class, 'destroy'])->name('question-bank.destroy');
    Route::post('/question-bank/bulk-delete', [QuestionBankController::class, 'bulkDestroy'])->name('question-bank.bulkDestroy');
    Route::get('/question-bank/import', [QuestionBankController::class, 'importForm'])->name('question-bank.importForm');
    Route::post('/question-bank/import', [QuestionBankController::class, 'import'])->name('question-bank.import');
    Route::get('/question-bank/import-json', [QuestionBankController::class, 'jsonImportForm'])->name('question-bank.jsonImportForm');
    Route::post('/question-bank/import-json/preview', [QuestionBankController::class, 'jsonPreview'])->name('question-bank.jsonPreview');
    Route::post('/question-bank/import-json', [QuestionBankController::class, 'jsonImport'])->name('question-bank.jsonImport');

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
