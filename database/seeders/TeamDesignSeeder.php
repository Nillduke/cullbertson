<?php
namespace Database\Seeders;
use App\Models\FormDefinition;
use Illuminate\Database\Seeder;
class TeamDesignSeeder extends Seeder
{
 public function run(): void {
  $form=FormDefinition::firstOrCreate(['key'=>'team-design'],['name'=>'Team directory & profile design copy','definition'=>[]]);
  $defaults=[
   'headline'=>'Good people. In your corner.',
   'intro'=>'Get to know the people behind your protection. A local team, a personal connection, and help making sense of your insurance.',
   'location'=>'FORT MYERS, FLORIDA',
   'directoryTitle'=>'Meet your Culbertson team',
   'directoryIntro'=>'Real people. Personal attention. Find a familiar face and get to know us a little better.',
   'directoryLabel'=>'OUR PEOPLE','memberLabel'=>'Culbertson Agency','profileLink'=>'View profile',
   'exploreLabel'=>'Meet the team','contactLabel'=>'Let us talk','profileEyebrow'=>'YOUR CULBERTSON TEAM',
   'bioEyebrow'=>'BEHIND THE NAME','bioTitle'=>'A little about',
   'otherTitle'=>'More friendly faces','otherIntro'=>'Get to know the rest of your Culbertson Agency team.',
   'allTeamLabel'=>'View the whole team','ctaEyebrow'=>'START A CONVERSATION',
   'ctaTitle'=>'Your next chapter. Our personal attention.',
   'ctaBody'=>'Have a question about coverage? Tell us what matters to you, and we can help you take the next step.',
   'ctaPrimary'=>'Contact our team','ctaSecondary'=>'Explore insurance',
   'contactUrl'=>'/contact-us/','insuranceUrl'=>'/insurance/',
  ];
  $form->update(['definition'=>array_merge($defaults,$form->definition??[])]);
 }
}
