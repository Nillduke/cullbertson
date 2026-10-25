<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\FeedbackEntry;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->isJson(), 415);
        abort_if(strlen($request->getContent()) > 12000, 413);
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'type' => ['required', 'in:suggestion,issue,compliment,other'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'page' => ['nullable', 'string', 'max:300'],
        ]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);
        $id = bin2hex(random_bytes(8));
        $record = array_merge($validator->validated(), ['id' => $id, 'createdAt' => gmdate('c')]);
        $saved = FeedbackEntry::create(['reference'=>$id,'payload'=>$record]);
        return $saved
            ? response()->json(['ok' => true, 'id' => $id], 201)->header('Cache-Control', 'no-store')
            : response()->json(['error' => 'We could not save your feedback. Please try again.'], 503);
    }
}
