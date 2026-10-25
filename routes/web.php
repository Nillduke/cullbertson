<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\{AdminAuthController,CmsController};
use App\Http\Middleware\EnsureCmsAdmin;
use App\Models\FormDefinition;

Route::get('/admin/login',[AdminAuthController::class,'login'])->name('login');
Route::post('/admin/login',[AdminAuthController::class,'authenticate'])->middleware('throttle:5,1');
Route::middleware(['auth',EnsureCmsAdmin::class])->prefix('admin')->group(function(){
    Route::post('/logout',[AdminAuthController::class,'logout']);
    Route::get('/',[CmsController::class,'dashboard']);
    Route::get('/content',[CmsController::class,'index']);
    Route::get('/media/picker',[CmsController::class,'mediaPicker']);
    Route::get('/pages/create',[CmsController::class,'create']);
    Route::post('/pages',[CmsController::class,'save']);
    Route::get('/pages/{page}/edit',[CmsController::class,'edit']);
    Route::post('/pages/{page}',[CmsController::class,'save']);
    Route::get('/globals',[CmsController::class,'globals']);
    Route::post('/blocks/{block}',[CmsController::class,'saveBlock']);
    Route::get('/forms',[CmsController::class,'forms']);
    Route::post('/forms/{form}',[CmsController::class,'saveForm']);
    Route::get('/enquiry-files/{id}',[\App\Http\Controllers\SupportRequestController::class,'download'])->whereNumber('id');
    Route::get('/inbox',[\App\Http\Controllers\SubmissionController::class,'index']);
    Route::get('/inbox/{kind}/{id}',[\App\Http\Controllers\SubmissionController::class,'show'])->where('kind','quotes|contacts|feedback')->whereNumber('id');
    Route::post('/inbox/{kind}/{id}/status',[\App\Http\Controllers\SubmissionController::class,'status'])->where('kind','quotes|contacts|feedback')->whereNumber('id');
    Route::get('/structure',[\App\Http\Controllers\StructureController::class,'index']);
    Route::post('/structure/{kind}/{id?}',[\App\Http\Controllers\StructureController::class,'save'])->whereNumber('id');
    Route::get('/media',[CmsController::class,'media']);
    Route::post('/media',[CmsController::class,'upload']);
    Route::post('/quotes/{quote}/status',[CmsController::class,'quoteStatus']);
    Route::post('/contacts/{contact}/status',[CmsController::class,'contactStatus']);
});
Route::get('/forms/{key}',function(string $key){$data=FormDefinition::data($key);if($key==='auto-options')$data['progress']=FormDefinition::data('quote-progress-copy');return response()->json($data)->header('Cache-Control','no-store');})->where('key','auto-options|services');

Route::get('/quote/progress',[\App\Http\Controllers\QuoteProgressController::class,'show'])->middleware('throttle:quote-progress');
Route::post('/quote/progress',[\App\Http\Controllers\QuoteProgressController::class,'store'])->middleware('throttle:quote-progress');
Route::get('/feedback', [QuoteController::class, 'token']);
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:feedback');
// Preserve old asset URLs while keeping native quote page paths free of physical folders.
Route::redirect('/auto-quote/form.css','/quote-assets/auto/form.css',301);
Route::redirect('/auto-quote/form.js','/quote-assets/auto/form.js',301);
Route::redirect('/quote/services.css','/quote-assets/services/services.css',301);
Route::redirect('/quote/services.js','/quote-assets/services/services.js',301);
Route::get('/auto-quote/submit', [QuoteController::class, 'token']);
Route::post('/auto-quote/submit', [QuoteController::class, 'auto'])->middleware('throttle:quotes');
Route::get('/quote/submit', [QuoteController::class, 'token']);
Route::post('/quote/submit', [QuoteController::class, 'service'])->middleware('throttle:quotes');
Route::get('/', [PageController::class, 'show'])->name('home');
Route::get('/wp-sitemap.xml',[\App\Http\Controllers\SitemapController::class,'index']);
Route::get('/wp-sitemap-{key}.xml',[\App\Http\Controllers\SitemapController::class,'entries'])->where('key','[a-z0-9_-]+');
Route::redirect('/sitemap_index.xml','/wp-sitemap.xml',301);
Route::redirect('/sitemap.xml','/wp-sitemap.xml',301);
Route::post('/contact-us/send',[\App\Http\Controllers\ContactController::class,'store'])->middleware('throttle:5,10');
Route::post('/requests/{kind}',[\App\Http\Controllers\SupportRequestController::class,'store'])->where('kind','careers|documents|classic')->middleware('throttle:5,10');
Route::get('/{path}', [PageController::class, 'show'])->where('path', '.*')->name('page');
