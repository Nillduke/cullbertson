<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('form_definitions')->where('key', 'services')->first();
        if (! $row) {
            return;
        }
        $data = json_decode($row->definition, true);
        $data['picker'] ??= json_decode(file_get_contents(database_path('import/services.json')), true)['picker'];
        DB::table('form_definitions')->where('id', $row->id)->update(['definition' => json_encode($data), 'updated_at' => now()]);
    }

    public function down(): void {}
};
