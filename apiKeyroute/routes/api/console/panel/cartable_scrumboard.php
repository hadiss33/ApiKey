<?php

use App\Http\Controllers\Api\Panel\V2\Scrumboard\BoardController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\ListController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\LabelController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\ChecklistController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\CommentController as ScrumboardCommentController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\CardController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\SprintController;
use App\Http\Controllers\Api\Panel\V2\Scrumboard\SprintReportController;
use App\Http\Controllers\Api\Panel\V2\Cartable\LetterController;
use App\Http\Controllers\Api\Panel\V2\Cartable\SubjectController;
use App\Http\Controllers\Api\Panel\V2\Cartable\SubjectStepsController;
use App\Http\Controllers\Api\Panel\V2\Cartable\RecipientController;
use App\Http\Controllers\Api\Panel\V2\Cartable\OperatorRoleController;
use App\Http\Controllers\Api\Panel\V2\Contact\ContactController;
use App\Http\Controllers\Api\Panel\V2\AI\MainController;
use App\Http\Controllers\Api\Panel\V2\Cartable\CartableMicroserviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('scrumboard')->middleware(['authWithJwt'])->group(function () {
    Route::resource('boards', BoardController::class);
    Route::resource('lists', ListController::class);
    Route::resource('labels', LabelController::class);
    Route::resource('checklists', ChecklistController::class);
    Route::resource('comments', ScrumboardCommentController::class);
    Route::resource('cards', CardController::class);
    Route::resource('sprints', SprintController::class);
    Route::resource('sprint_reports', SprintReportController::class);
});

Route::prefix('cartable-license')->middleware(['authWithJwt'])->group(function () {
    Route::resource('letters', LetterController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('subject_steps', SubjectStepsController::class);
    Route::resource('recipients', RecipientController::class);
    Route::resource('operator_role', OperatorRoleController::class);
    Route::put('letters/step/{id}', [LetterController::class, 'updateLetterStep']);
    Route::post('letters/step/{id}/back', [LetterController::class, 'revertToStep']);
});

Route::middleware(['authWithJwt'])->group(function () {
    Route::resource('contacts', ContactController::class);
});

Route::prefix('ai')->controller(MainController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('chat', 'chat');
    Route::post('chat/completions', 'getReport1Gap');
    Route::post('generate/content/{type}', 'generateContent');
});

// Cartable Microservice
Route::prefix('cartable')->controller(CartableMicroserviceController::class)->middleware(['authWithJwt'])->group(function () {

    // LETTERS
    Route::get('letters', 'indexLetters');
    Route::get('letters/{id}', 'showLetter');
    Route::post('letters', 'storeLetter');
    Route::post('letters/{id}', 'updateLetter');   // use for file
    Route::delete('letters/{id}', 'destroyLetter');
    Route::post('letters/feedback/{id}', 'feedbackLetter');

    // SUBJECTS
    Route::get('categories/mini-list', 'categoriesMiniList');
    Route::get('categories', 'indexCategories');
    Route::post('categories', 'storeCategory');
    Route::get('categories/{id}', 'showCategory');
    Route::put('categories/{id}', 'updateCategory');
    Route::patch('categories/{id}', 'patchCategory');
    Route::delete('categories/{id}', 'destroyCategory');

    // SUBJECT STEPS
    Route::get('category_steps', 'indexCategorySteps');
    Route::post('category_steps', 'storeCategoryStep');
    Route::get('category_steps/{id}', 'showCategoryStep');
    Route::put('category_steps/{id}', 'updateCategoryStep');
    Route::patch('category_steps/{id}', 'patchCategoryStep');
    Route::delete('category_steps/{id}', 'destroyCategoryStep');

    // LETTER STEPS
    Route::get('letter_steps', 'indexLetterSteps');
    Route::post('letter_steps', 'storeLetterStep');
    Route::get('letter_steps/{id}', 'showLetterStep');
    Route::put('letter_steps/{id}', 'updateLetterStep');
    Route::patch('letter_steps/{id}', 'patchLetterStep');
    Route::delete('letter_steps/{id}', 'destroyLetterStep');

    // PROCESS
    Route::post('letters/{id}/process', 'processLetter');
    Route::post('letters/{id}/return_to', 'returnToStep');

    // SPAM BLOCKS
    Route::get('spam_blocks', 'indexSpamBlocks');
    Route::post('spam_blocks', 'storeSpamBlock');
    Route::get('spam_blocks/{id}', 'showSpamBlock');
    Route::put('spam_blocks/{id}', 'updateSpamBlock');
    Route::delete('spam_blocks/{id}', 'destroySpamBlock');
    Route::post('spam_blocks/{id}/toggleStatus', 'toggleSpamBlockStatus');

    // ORGANIZATIONS
    Route::prefix('organizations')->group(function () {
        Route::get('/', 'indexOrganizations');
        Route::post('/', 'storeOrganization');
        Route::get('mini-list', 'organizationsMiniList');
        Route::get('{id}', 'showOrganization');
        Route::put('{id}', 'updateOrganization');
        Route::delete('{id}', 'destroyOrganization');
    });
});
