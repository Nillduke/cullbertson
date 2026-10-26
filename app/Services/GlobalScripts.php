<?php

namespace App\Services;

use App\Models\{FormDefinition,Page,PageSection,SiteBlock};

class GlobalScripts
{
    public function settings():array
    {
        return array_replace(['header'=>'','footer'=>'','thank_you'=>''],FormDefinition::where('key','global-scripts')->first()?->definition??[]);
    }

    public function inventory():array
    {
        $scripts=[];
        foreach(['app.js'=>'Navigation, feedback and homepage interactions','exit-intent.js'=>'Exit popup','tracking.js'=>'Google Analytics and quote conversions','quote-progress.js'=>'Saved quote progress'] as $path=>$name)$scripts[$path]=['name'=>$name,'path'=>$path,'scope'=>in_array($path,['tracking.js','quote-progress.js'])?'When the relevant feature is enabled':'Public website'];
        foreach(Page::published()->get(['presentation']) as $page)foreach($page->presentation['scripts']??[] as $path)$scripts[$path]=['name'=>'Page script','path'=>$path,'scope'=>'Relevant pages only'];
        foreach(array_merge(SiteBlock::pluck('content_html')->all(),PageSection::pluck('content_html')->all(),array_values($this->settings())) as $html){
            if(!is_string($html))continue;
            preg_match_all('~<script\b[^>]*\bsrc=[\'"]([^\'"]+)[\'"]~i',$html,$matches);
            foreach($matches[1] as $path)$scripts[$path]=['name'=>'Embedded script','path'=>$path,'scope'=>'Saved content / custom scripts'];
        }
        return array_values($scripts);
    }
}
