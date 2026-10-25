@php($copy=\App\Models\FormDefinition::data('form-information')['request'])
<section class="sp-shell sp-section privacy-request" id="privacy-request" aria-labelledby="privacy-request-title">
    <div class="privacy-request-intro"><p class="sp-eyebrow">{{ $copy['eyebrow'] }}</p><h2 id="privacy-request-title">{{ $copy['title'] }}</h2><p>{{ $copy['intro'] }}</p><p>{{ $copy['sensitiveNote'] }}</p></div>
    <div class="privacy-request-panel">
        @if(session('privacy_reference'))<div role="status" class="privacy-request-success"><h3>{{ $copy['successTitle'] }}</h3><p>{{ $copy['successBody'] }}</p><strong>{{ session('privacy_reference') }}</strong></div>@endif
        @if($errors->any())<div role="alert" class="privacy-request-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form action="{{ url('/privacy-choices/request') }}" method="post" class="privacy-request-form">@csrf
            <label>{{ $copy['name'] }}<input name="name" autocomplete="name" maxlength="100" value="{{ old('name') }}" required></label>
            <label>{{ $copy['email'] }}<input name="email" type="email" autocomplete="email" maxlength="254" value="{{ old('email') }}" required></label>
            <label>{{ $copy['phone'] }}<input name="phone" type="tel" autocomplete="tel" maxlength="40" value="{{ old('phone') }}"></label>
            <label>{{ $copy['type'] }}<select name="request_type" required>@foreach($copy['types'] as $key=>$label)<option value="{{ $key }}" @selected(old('request_type',request('request'))===$key)>{{ $label }}</option>@endforeach</select></label>
            <label>{{ $copy['message'] }}<textarea name="message" rows="4" maxlength="3000">{{ old('message') }}</textarea></label>
            <div class="privacy-request-trap" aria-hidden="true"><label>Website<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
            <label class="privacy-request-check"><input name="authorized" type="checkbox" value="1" required @checked(old('authorized'))><span>{{ $copy['authorized'] }}</span></label>
            <p>{{ $copy['processing'] }}</p><button type="submit">{{ $copy['submit'] }} <span aria-hidden="true">→</span></button>
        </form>
    </div>
</section>
