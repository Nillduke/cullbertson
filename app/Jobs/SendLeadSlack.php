<?php
namespace App\Jobs;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\LeadSlack;
class SendLeadSlack {use Queueable;public function __construct(public int $deliveryId){}public function handle(LeadSlack $slack):void{$slack->deliver($this->deliveryId);}}
