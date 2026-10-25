<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content,Envelope,Headers};

class AgencyNotification extends Mailable {
 public function __construct(public array $details,public string $mailSubject,public string $messageDomain){}
 public function envelope():Envelope{return new Envelope(subject:$this->mailSubject);}
 public function content():Content{return new Content(view:'emails.agency-notification');}
 public function headers():Headers{return new Headers(messageId:'culbertson-mail-'.$this->details['deliveryId'].'@'.$this->messageDomain);}
}
