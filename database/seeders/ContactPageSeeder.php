<?php
namespace Database\Seeders;
use App\Models\{Page,FormDefinition};
use Illuminate\Database\Seeder;
class ContactPageSeeder extends Seeder {
    public function run():void {
        FormDefinition::firstOrCreate(['key'=>'contact-page'],['name'=>'Contact page content & form','definition'=>[
            'heading'=>'Contact us','subtitle'=>'Local people. Real answers. Here for your Florida life.',
            'heroImage'=>'/assets/florida-neighborhood.webp',
            'tabs'=>[
                ['label'=>'Existing customers','cards'=>[
                    ['icon'=>'question','heading'=>'Before you call: find helpful answers','body'=>'Explore practical guides to understand your insurance and get the most from your coverage.','linkLabel'=>'Browse insurance articles','url'=>'/expert-insurance-insights-and-tips-culbertson-agency-articles/'],
                    ['icon'=>'phone','heading'=>'(239) 210-7700','body'=>'Monday–Thursday: 8:30am–5:30pm\nFriday: 8:30am–5:00pm','linkLabel'=>'Call our team','url'=>'tel:12392107700'],
                    ['icon'=>'shield','heading'=>'Need help with a claim?','body'=>'Find claims information and the next steps for getting support.','linkLabel'=>'Get claims support','url'=>'/claims/'],
                    ['icon'=>'message','heading'=>'Policy, billing or coverage questions','body'=>'Send our local team a message about your policy or an insurance question.','linkLabel'=>'Send us a message','url'=>'#contact-form'],
                    ['icon'=>'pin','heading'=>'Visit our Fort Myers office','body'=>'13099 S Cleveland Ave #310\nFort Myers, FL 33907','linkLabel'=>'Get directions','url'=>'https://www.google.com/maps/search/?api=1&query=13099+S+Cleveland+Ave+310+Fort+Myers+FL+33907'],
                    ['icon'=>'document','heading'=>'Fax us','body'=>'(239) 210-7701','linkLabel'=>'Ask about sending documents','url'=>'#contact-form'],
                ]],
                ['label'=>'New to Culbertson?','cards'=>[
                    ['icon'=>'shield','heading'=>'Find insurance for your Florida life','body'=>'Explore coverage for your home, car, family and everyday adventures.','linkLabel'=>'Explore our insurance','url'=>'/insurance/'],
                    ['icon'=>'phone','heading'=>'Talk with a local agent','body'=>'Call (239) 210-7700 during our office hours. We can help you explore your options.','linkLabel'=>'Call our team','url'=>'tel:12392107700'],
                    ['icon'=>'document','heading'=>'Ready for a quote?','body'=>'Tell us what you would like to protect and start your quote online.','linkLabel'=>'Get a quote','url'=>'/get-a-quote/'],
                    ['icon'=>'message','heading'=>'Not sure where to start?','body'=>'Send a question and tell us a little about what you need.','linkLabel'=>'Ask our team','url'=>'#contact-form'],
                ]],
            ],
            'formEyebrow'=>'LET’S START A CONVERSATION','formTitle'=>'Send us a message','formIntro'=>'Tell us how we can help. Our Fort Myers team looks forward to hearing from you.',
            'labels'=>['name'=>'Full name','email'=>'Email address','phone'=>'Phone number (optional)','topic'=>'How can we help?','message'=>'Your message'],
            'topics'=>[['value'=>'quote','label'=>'I would like a quote'],['value'=>'policy','label'=>'Policy or coverage question'],['value'=>'billing','label'=>'Billing question'],['value'=>'claim','label'=>'Claims question'],['value'=>'other','label'=>'Something else']],
            'topicPlaceholder'=>'Select a topic','submit'=>'Send message','privacyText'=>'We’ll use your contact details to respond to your enquiry.','privacyLabel'=>'Privacy policy','privacyUrl'=>'/privacy-policy-2/',
            'successTitle'=>'Thank you. Your message has been received.','successBody'=>'Your enquiry reference is','errorHeading'=>'Please check the details below.',
            'officeEyebrow'=>'YOUR LOCAL INSURANCE TEAM','officeTitle'=>'A friendly voice. A familiar place.','officeBody'=>'Call or visit our Fort Myers office for personal help with your insurance.',
            'officePhone'=>'(239) 210-7700','officePhoneUrl'=>'tel:12392107700','officeAddress'=>'13099 S Cleveland Ave #310\nFort Myers, FL 33907',
            'officeHours'=>'Mon–Thu: 8:30am–5:30pm\nFri: 8:30am–5:00pm','directionsLabel'=>'Get directions','directionsUrl'=>'https://www.google.com/maps/search/?api=1&query=13099+S+Cleveland+Ave+310+Fort+Myers+FL+33907',
        ]]);
        $page=Page::where('slug','contact-us')->first();
        if($page){$presentation=$page->presentation??[];$presentation['template']='contact';$page->update(['presentation'=>$presentation]);}
    }
}
