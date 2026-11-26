<?php

namespace App\Http\Controllers;

use App\Services\QuoteValidator;
use App\Services\QuoteProgress;
use App\Services\QuoteThankYou;
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

    public function landingAuto(Request $request, QuoteProgress $progress, QuoteThankYou $thankYou): JsonResponse
    {
        $this->checkPayload($request);
        $input = $request->validate([
            'flow_id' => 'required|uuid',
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email:rfc|max:254',
            'phone' => 'required|string|max:24',
            'ownership' => 'required|in:own,lease',
            'insured' => 'required|in:Yes,No',
            'carrier' => 'required|in:State Farm,Geico,Allstate,Other,Currently not insured',
            'zip' => ['required','regex:/^\\d{5}$/'],
            'sms' => 'nullable|boolean',
        ]);
        abort_if(($input['insured'] === 'No') !== ($input['carrier'] === 'Currently not insured'), 422, 'Please check your insurance carrier selection.');
        $phone = preg_replace('/\\D/', '', $input['phone']);
        if (strlen($phone) === 11 && $phone[0] === '1') $phone = substr($phone, 1);
        abort_unless(preg_match('/^[2-9]\\d{9}$/D', $phone), 422, 'Enter a valid 10-digit US phone number.');
        $sms = ($input['sms'] ?? false) === true;
        $name = trim($input['first_name']).' '.trim($input['last_name']);
        $payload = [
            'request' => [
                'name' => $name,
                'email' => strtolower(trim($input['email'])),
                'phone' => $phone,
                'vehicleOwnership' => $input['ownership'] === 'own' ? 'Own' : 'Lease',
                'insured' => $input['insured'],
                'carrier' => $input['carrier'],
                'address' => ['state' => 'FL', 'zip' => $input['zip']],
                'smsRequested' => $sms,
            ],
            'service' => 'auto',
            'serviceName' => 'Auto insurance',
            'mode' => 'auto-insurance-landing',
            'smsConsent' => $sms,
            'smsConsentText' => $sms ? QuoteValidator::SMS_CONSENT_TEXT : null,
            'smsConsentAt' => $sms ? now()->toIso8601String() : null,
            'smsConsentVersion' => $sms ? 'quote-sms-v1' : null,
        ];
        $saved = $progress->complete($request, 'auto', $payload);
        return response()->json([
            'ok' => true,
            'reference' => $saved->reference,
            'redirect' => $thankYou->issue($request, $saved),
        ], 201)->header('Cache-Control', 'no-store');
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
