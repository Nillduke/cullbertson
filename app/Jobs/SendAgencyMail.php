<?php
namespace App\Jobs;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\LeadMail;
class SendAgencyMail {
 use Queueable;
 public function __construct(public int $deliveryId){}
 public function handle(LeadMail $mail):void {$mail->deliver($this->deliveryId);}
}
