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
        if (! is_array($copy)) {
            return;
        }

        $copy['emailTitle'] = 'How can we reach you?';
        $copy['emailDescription'] = 'Enter your email address and mobile number so our team can help with your request.';

        DB::table('form_definitions')->where('key', 'quote-progress-copy')->update([
            'definition' => json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The combined contact step uses this copy, so keep it on rollback.
    }
};
