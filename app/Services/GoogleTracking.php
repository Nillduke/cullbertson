<?php

namespace App\Services;

use App\Models\FormDefinition;

class GoogleTracking
{
    public function noscriptAllowed(array $data): bool
    {
        return '/'.trim(request()->path(),'/')!==rtrim($data['legacyAutoConfirmationPath']??'','/');
    }

    public function configuration(): ?array
    {
        if (!config('tracking.enabled') || request()->is('admin','admin/*')) return null;
        $data=FormDefinition::where('key','google-tracking')->first()?->definition;
        if (!$data) return null;
        foreach (['containerId'=>'~^GTM-[A-Z0-9]+$~D','primaryAnalyticsId'=>'~^G-[A-Z0-9]+$~D','formsAnalyticsId'=>'~^G-[A-Z0-9]+$~D','additionalAnalyticsId'=>'~^G-[A-Z0-9]+$~D','additionalGoogleTagId'=>'~^GT-[A-Z0-9]+$~D','adsId'=>'~^AW-[0-9]+$~D','adsConversionLabel'=>'~^[A-Za-z0-9_-]+$~D'] as $key=>$pattern) {
            if (!is_string($data[$key]??null) || !preg_match($pattern,$data[$key])) return null;
        }
        $data['useContainer']=$this->noscriptAllowed($data);
        return $data;
    }
}
