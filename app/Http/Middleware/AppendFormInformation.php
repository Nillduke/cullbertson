<?php

namespace App\Http\Middleware;

use App\Models\FormDefinition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AppendFormInformation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('admin', 'admin/*') || ! $response instanceof Response || $response->getStatusCode() !== 200 || ! str_contains($response->headers->get('Content-Type', ''), 'text/html')) return $response;
        $html = $response->getContent();
        // Only the dedicated quote workspaces need this disclosure. Other pages
        // can contain feedback, contact or quote teaser forms without receiving it.
        if (! preg_match('~<form\b[^>]*\bid="(?:auto-quote-wizard|service-quote-wizard)"~i', $html)) return $response;
        $legal = FormDefinition::where('key', 'form-information')->first()?->definition;
        if (! $legal) return $response;
        $full = view('partials.form-information', ['legal'=>$legal, 'compact'=>false])->render();
        // Quote workspaces have a sidebar: keep their policy information below the full workspace.
        $html = preg_replace('~</main>~i', '</main>'.$full, $html, 1);
        $original = $response->original;
        $response->setContent($html);
        $response->original = $original;
        return $response;
    }
}
