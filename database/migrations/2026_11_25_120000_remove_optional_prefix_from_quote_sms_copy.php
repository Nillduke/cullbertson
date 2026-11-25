<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $definition = DB::table('form_definitions')->where('key', 'quote-progress-copy')->value('definition');
        if (! is_string($definition)) {
            return;
        }

        $copy = json_decode($definition, true);
        if (! is_array($copy) || ! isset($copy['sms']) || ! is_string($copy['sms'])) {
            return;
        }

        $copy['sms'] = preg_replace('/^Optional:\\s*/i', '', $copy['sms']);
        DB::table('form_definitions')->where('key', 'quote-progress-copy')->update([
            'definition' => json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the updated customer-facing copy when rolling back application code.
    }
};
