@extends('layouts.app')
@section('content')
<main id="main" class="contact-main">
    <section class="contact-hero" aria-labelledby="contact-title" style="--contact-hero:url('{{ $contact['heroImage'] }}')"><div class="contact-shell"><h1 id="contact-title">{{ $contact['heading'] }}</h1><p>{{ $contact['subtitle'] }}</p></div></section>
    <div class="contact-shell contact-support">
        <div class="contact-tabs" role="tablist" aria-label="{{ $contact['heading'] }}">
            @foreach($contact['tabs'] as $tab)<button type="button" role="tab" id="contact-tab-{{ $loop->index }}" aria-controls="contact-panel-{{ $loop->index }}" aria-selected="{{ $loop->first?'true':'false' }}" tabindex="{{ $loop->first?'0':'-1' }}">{{ $tab['label'] }}</button>@endforeach
        </div>
        @foreach($contact['tabs'] as $tab)
        <section class="contact-tab-panel" role="tabpanel" id="contact-panel-{{ $loop->index }}" aria-labelledby="contact-tab-{{ $loop->index }}" tabindex="0" @if(!$loop->first) hidden @endif><div class="contact-options">
            @foreach($tab['cards'] as $card)<div class="contact-option"><span class="contact-option-icon" aria-hidden="true">@include('content.contact-icon',['icon'=>$card['icon']])</span><div><h2>{{ $card['heading'] }}</h2><p>{{ str_replace('\\n',"\n",$card['body']) }}</p><a href="{{ $card['url'] }}">{{ $card['linkLabel'] }} <span aria-hidden="true">→</span></a></div></div>@endforeach
        </div></section>
        @endforeach
    </div>
    <section class="contact-shell contact-bottom" id="contact-form" aria-labelledby="contact-form-title">
        <div class="contact-form-card"><p class="contact-eyebrow">{{ $contact['formEyebrow'] }}</p><h2 id="contact-form-title">{{ $contact['formTitle'] }}</h2><p class="contact-form-intro">{{ $contact['formIntro'] }}</p>
        @if(session('contact_reference'))<div class="contact-success" role="status" tabindex="-1" data-contact-notice><h3>{{ $contact['successTitle'] }}</h3><p>{{ $contact['successBody'] }} <strong>{{ session('contact_reference') }}</strong>.</p></div>@endif
        @if($errors->any())<div class="contact-form-errors" role="alert" tabindex="-1" data-contact-notice><strong>{{ $contact['errorHeading'] }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="/contact-us/send" class="contact-message-form">@csrf
            <div class="contact-form-grid">
                <label>{{ $contact['labels']['name'] }} <span aria-hidden="true">*</span><input name="name" autocomplete="name" value="{{ old('name') }}" maxlength="100" required @if($errors->has('name')) aria-invalid="true" @endif></label>
                <label>{{ $contact['labels']['email'] }} <span aria-hidden="true">*</span><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" maxlength="254" required @if($errors->has('email')) aria-invalid="true" @endif></label>
                <label>{{ $contact['labels']['phone'] }}<input type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" maxlength="40"></label>
                <label>{{ $contact['labels']['topic'] }} <span aria-hidden="true">*</span><select name="topic" required @if($errors->has('topic')) aria-invalid="true" @endif><option value="">{{ $contact['topicPlaceholder'] }}</option>@foreach($contact['topics'] as $topic)<option value="{{ $topic['value'] }}" @selected(old('topic')===$topic['value'])>{{ $topic['label'] }}</option>@endforeach</select></label>
                <label class="contact-full">{{ $contact['labels']['message'] }} <span aria-hidden="true">*</span><textarea name="message" rows="6" minlength="10" maxlength="3000" required @if($errors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea></label>
            </div>
            <div class="contact-trap" aria-hidden="true"><label>Website<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
            <p class="contact-privacy">{{ $contact['privacyText'] }} <a href="{{ $contact['privacyUrl'] }}">{{ $contact['privacyLabel'] }}</a></p><button class="contact-submit" type="submit">{{ $contact['submit'] }} <span aria-hidden="true">→</span></button>
        </form></div>
        <aside class="contact-office"><p class="contact-eyebrow">{{ $contact['officeEyebrow'] }}</p><h2>{{ $contact['officeTitle'] }}</h2><p>{{ $contact['officeBody'] }}</p><a class="contact-office-phone" href="{{ $contact['officePhoneUrl'] }}">{{ $contact['officePhone'] }}</a><p class="contact-office-lines">{{ str_replace('\\n',"\n",$contact['officeAddress']) }}</p><p class="contact-office-lines">{{ str_replace('\\n',"\n",$contact['officeHours']) }}</p><a class="contact-directions" href="{{ $contact['directionsUrl'] }}">{{ $contact['directionsLabel'] }} <span aria-hidden="true">↗</span></a></aside>
    </section>
</main>
@endsection
