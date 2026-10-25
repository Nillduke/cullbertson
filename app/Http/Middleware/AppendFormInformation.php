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
        if (stripos($html, '<form') === false) return $response;
        $legal = FormDefinition::where('key', 'form-information')->first()?->definition;
        if (! $legal) return $response;
        $full = view('partials.form-information', ['legal'=>$legal, 'compact'=>false])->render();
        $compact = view('partials.form-information', ['legal'=>$legal, 'compact'=>true])->render();
        $wizard = false;
        $html = preg_replace_callback('~<form\b[^>]*>.*?</form>~is', function ($match) use ($full, $compact, &$wizard) {
            if (preg_match('~\bid="(?:auto-quote-wizard|service-quote-wizard)"~', $match[0])) {
                $wizard = true;
                return $match[0];
            }
            return $match[0].(preg_match('~\bid="(?:coverage-quote-form|feedback-form)"~', $match[0]) ? $compact : $full);
        }, $html);
        // Quote workspaces have a sidebar: keep their policy information below the full workspace.
        if ($wizard) $html = preg_replace('~</main>~i', '</main>'.$full, $html, 1);
        $original = $response->original;
        $response->setContent($html);
        $response->original = $original;
        return $response;
    }
}
