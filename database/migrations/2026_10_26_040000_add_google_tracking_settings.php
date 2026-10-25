<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void { (new \Database\Seeders\GoogleTrackingSeeder)->run(); }
    public function down(): void {}
};
