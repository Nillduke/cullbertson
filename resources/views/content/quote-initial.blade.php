@if($picker)
    <p class="wizard-eyebrow">{{ $catalog['picker']['eyebrow'] }}</p>
    <h1 id="wizard-title">{{ $catalog['picker']['title'] }}</h1>
    <p class="wizard-description">{{ $catalog['picker']['description'] }}</p>
    <div class="service-picker">
        <a class="service-tile" href="/auto-quote/"><img src="/assets/quote-service-auto.svg" width="53" height="45" alt=""><span><strong>{{ $catalog['picker']['autoLabel'] }}</strong><small>{{ $catalog['picker']['autoDescription'] }}</small></span><b aria-hidden="true">↗</b></a>
        @foreach($catalog['services'] as $key => $item)
            <a class="service-tile" href="{{ !empty($item['path']) ? '/get-a-quote/'.$item['path'].'/' : '/quote/?service='.$key }}"><img src="/assets/quote-{{ $item['icon'] }}.svg" width="53" height="45" alt=""><span><strong>{{ $item['name'] }}</strong><small>{{ $key === 'home-auto' ? $catalog['picker']['bundleDescription'] : $catalog['picker']['serviceDescription'] }}</small></span><b aria-hidden="true">↗</b></a>
        @endforeach
    </div>
@else
    <p class="wizard-eyebrow">{{ $auto ? 'Contact' : $service['name'].' INSURANCE · Contact' }}</p>
    <h1 id="wizard-title" tabindex="-1">{{ $copy['emailTitle'] }}</h1>
    <p class="wizard-description">{{ $copy['emailDescription'] }}</p>
    <div class="{{ $auto ? 'wizard-two-fields' : 'service-fields wizard-two-fields' }}">
        <div class="wizard-field"><label for="{{ $auto ? 'wizard-email' : 'service-email' }}">Email address</label><input id="{{ $auto ? 'wizard-email' : 'service-email' }}" name="email" value="" type="email" required maxlength="254" autocomplete="email" placeholder="you@example.com"></div>
        <div class="wizard-field"><label for="{{ $auto ? 'wizard-phone' : 'service-phone' }}">Mobile phone number</label><input id="{{ $auto ? 'wizard-phone' : 'service-phone' }}" name="phone" value="" type="tel" required maxlength="24" inputmode="tel" autocomplete="tel" placeholder="(239) 555-0123"></div>
    </div>
    <label class="wizard-check"><input type="checkbox" name="sms"><span>I agree that Culbertson Agency may call or text me at the number provided about my insurance request, including using automated technology. Message and data rates may apply. Reply STOP to opt out. Consent is not a condition of purchase. <a href="/privacy-policy-2/" target="_blank" rel="noopener">Privacy Policy</a> and <a href="/sms-terms-of-service/" target="_blank" rel="noopener">SMS Terms of Service</a>.</span></label>
    <p class="quote-progress-notice">{{ $copy['notice'] }} <a href="/privacy-policy-2/" target="_blank" rel="noopener">Privacy policy</a></p>
@endif
