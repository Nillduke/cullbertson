<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::table('pages',fn(Blueprint $table)=>$table->json('insurance_content')->nullable()); }
    public function down():void { Schema::table('pages',fn(Blueprint $table)=>$table->dropColumn('insurance_content')); }
};
