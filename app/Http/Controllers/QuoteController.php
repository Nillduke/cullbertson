<?php

namespace App\Http\Controllers;

use App\Services\QuoteValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\QuoteRequest;

class QuoteController extends Controller
{
    public function token(): JsonResponse
    {
        return response()->json(['token' => csrf_token()])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function auto(Request $request, QuoteValidator $validator): JsonResponse
    {
        $this->checkPayload($request);
        return $this->save('auto', ['request' => $validator->auto($request->all())]);
    }

    public function service(Request $request, QuoteValidator $validator): JsonResponse
    {
        $this->checkPayload($request);
        $validated = $validator->service($request->all());
        return $this->save($validated['service'], $validated);
    }

    private function checkPayload(Request $request): void
    {
        abort_unless($request->isJson(), 415, 'Please use the quote form.');
        abort_if(strlen($request->getContent()) > 24000, 413, 'This request is too large.');
    }

    private function save(string $service, array $data): JsonResponse
    {
        $reference = 'CUL-'.gmdate('ymd').'-'.strtoupper(bin2hex(random_bytes(6)));
        $answers = $data['request'] ?? $data['answers'] ?? [];
        $smsConsent = ($answers['smsRequested'] ?? $answers['sms'] ?? false) === true;
        $record = array_merge($data, [
            'reference' => $reference, 'service' => $service,
            'createdAt' => gmdate('c'), 'mode' => 'local-preview',
            'consentText' => 'I agree that Culbertson Agency may contact me about this quote request using the details provided.',
            'smsConsent' => $smsConsent,
            'smsConsentText' => $smsConsent ? QuoteValidator::SMS_CONSENT_TEXT : null,
            'smsConsentAt' => $smsConsent ? now()->toIso8601String() : null,
            'smsConsentVersion' => $smsConsent ? 'quote-sms-v1' : null,
        ]);
        $saved = app(\App\Services\QuoteProgress::class)->complete(request(),$service,$record);
        if (!$saved) return response()->json(['error' => 'We could not save your request. Please try again.'], 503);
        return response()->json(['ok' => true, 'reference' => $saved->reference, 'mode' => 'local-preview', 'redirect'=>app(\App\Services\QuoteThankYou::class)->issue(request(),$saved)], 201)->header('Cache-Control', 'no-store');
    }
}
