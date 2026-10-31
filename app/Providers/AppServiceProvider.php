<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Models\SiteBlock;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No external services are required for local previews.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($publicPath=config('app.public_path')) $this->app->usePublicPath($publicPath);
        \App\Models\QuoteRequest::observe(\App\Observers\LeadMailObserver::class);
        \App\Models\ContactEnquiry::observe(\App\Observers\LeadMailObserver::class);
        \App\Models\FeedbackEntry::observe(\App\Observers\LeadMailObserver::class);
        \App\Models\QuoteRequest::observe(\App\Observers\LeadSlackObserver::class);
        \App\Models\ContactEnquiry::observe(\App\Observers\LeadSlackObserver::class);
        \App\Models\FeedbackEntry::observe(\App\Observers\LeadSlackObserver::class);
        View::composer('layouts.app',function($view){
            $view->with('seo',app(\App\Services\SeoMetadata::class)->forRequest(request(),$view->getData()));
            $blocks=SiteBlock::pluck('content_html','key')->all();
            $blocks['exit-intent']=str_replace('%%EXIT_PREVIEW%%',app()->environment('local')&&request()->boolean('preview_exit')?'true':'false',$blocks['exit-intent']??'');
            $view->with('siteBlocks',$blocks);
            $view->with('siteCopy',\App\Models\FormDefinition::where('key','site-copy')->first()?->definition??[]);
        });
        RateLimiter::for('quote-progress',fn(Request $request)=>Limit::perMinutes(10,180)->by($request->session()->getId()));
        RateLimiter::for('quotes', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by($request->session()->getId())
            ->response(fn () => response()->json(['error' => 'Please wait before saving another quote request.'], 429)));
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(5)
            ->by($request->session()->getId())
            ->response(fn () => response()->json(['error' => 'Please wait a minute before sending more feedback.'], 429)));
    }
}
