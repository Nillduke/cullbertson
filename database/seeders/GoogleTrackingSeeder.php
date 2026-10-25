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
            'name'=>'Google tracking destinations',
            'definition'=>json_decode(file_get_contents(database_path('import/google-tracking.json')),true,flags:JSON_THROW_ON_ERROR),
        ]);
        if ($page=Page::where('slug','cookie-policy')->first()) {
            if (!$page->sections()->where('component','google_tracking_notice')->exists()) $page->sections()->create([
                'name'=>'Google measurement and privacy settings','component'=>'google_tracking_notice',
                'position'=>(int)$page->sections()->max('position')+1,
                'content_html'=>self::noticeHtml(),
            ]);
        }
    }
    public static function noticeHtml(): string
    {
        return <<<'HTML'
<section class="sp-shell sp-section sp-rich legal-copy"><h2 id="google-measurement">Google Analytics and advertising measurement</h2><p>This website loads Google Tag Manager, Google Analytics and Google Ads measurement tags when a public page opens. These can use cookies and related identifiers to measure visits, completed quote requests and advertising performance. The website does not require an analytics consent choice before loading these tags.</p><p>The agency also retains separate form and additional Analytics destinations. New quote-completion events contain an opaque transaction identifier and coverage category; the application does not pass your name, email address, phone number, address or quote answers with these events. Page-location and referrer parameters supplied by this integration omit query strings and fragments.</p><p>You can use your browser's cookie and tracking controls to restrict this technology. The Privacy Choices form sends a request to our agency team; it is not an automated switch that disables Google tags in your browser. Browser controls do not remove information already received by Google or delete records saved by the agency.</p><p>Google's handling of information is described in its <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a>. Hosting-provider services may operate separately from these agency Google tags. Website measurement does not add permission for marketing calls or texts and does not change insurance coverage.</p></section>
HTML;
    }

}
