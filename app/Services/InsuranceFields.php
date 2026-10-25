<?php
namespace App\Services;
class InsuranceFields {
    public static function groups():array { return [
        'Heading & navigation'=>['heading','eyebrow','subtitle','breadcrumb','home_label','insurance_label','hero','hero_alt'],
        'Introduction'=>['intro_heading','intro_body','explore_label'],
        'Quote panel'=>['quote_eyebrow','quote_heading','quote_body','quote_url','quote_button','zip_label','zip_placeholder','zip_validation','phone_intro','phone','phone_url'],
        'Feature panel'=>['feature_eyebrow','feature_heading','feature_body','feature_image','feature_image_alt','feature_link_label'],
        'Coverage section'=>['coverage_eyebrow','coverage_heading','coverage_intro','coverage_note'],
        'Questions & resources'=>['faq_eyebrow','faq_heading','resources_label'],
    ]; }
    public static function keys():array { return array_merge(...array_values(self::groups())); }
    public static function defaults():array { return \App\Models\FormDefinition::where('key','insurance-defaults')->first()?->definition??[]; }
}
