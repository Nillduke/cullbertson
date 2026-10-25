<?php

namespace Database\Seeders;

use App\Models\{FormDefinition,Page};
use Illuminate\Database\Seeder;

class GoogleTrackingSeeder extends Seeder
{
    public function run(): void
    {
        if (!Page::where('slug','')->exists()) return;
        FormDefinition::firstOrCreate(['key'=>'google-tracking'], [
            'name'=>'Google tracking destinations and visitor privacy choices',
            'definition'=>json_decode(file_get_contents(database_path('import/google-tracking.json')),true,flags:JSON_THROW_ON_ERROR),
        ]);
        if ($page=Page::where('slug','cookie-policy')->first()) {
            if (!$page->sections()->where('component','google_tracking_notice')->exists()) $page->sections()->create([
                'name'=>'Google measurement and privacy settings','component'=>'google_tracking_notice',
                'position'=>(int)$page->sections()->max('position')+1,
                'content_html'=>'<section class="sp-shell sp-section sp-rich legal-copy"><h2 id="google-measurement">Google Analytics and advertising measurement</h2><p>When you allow analytics and ads through our privacy banner, Google Tag Manager loads Google Analytics and Google Ads measurement tags. These can use cookies and related identifiers to measure visits, completed quote requests and advertising performance. We also retain the agency\'s separate form Analytics and additional Analytics destinations.</p><p>Optional Google tags remain blocked before you make a choice and when you choose Only necessary. The new quote-completion events contain an opaque transaction identifier and the coverage category; they do not contain your name, email address, phone number, address or quote answers. Query strings are removed from the page locations supplied by this integration.</p><p>Use the persistent Privacy settings button to allow or decline analytics and ads. Changing to Only necessary refreshes the page so optional Google scripts are no longer loaded. This does not erase browser cookies or information already received by Google; use browser controls and Privacy Choices for further requests.</p><p>Google\'s handling of information is described in its <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a>. Hosting-provider services may operate separately from these agency Google tags. Your analytics choice does not change contact permission or insurance coverage.</p></section>',
            ]);
        }
    }
}
