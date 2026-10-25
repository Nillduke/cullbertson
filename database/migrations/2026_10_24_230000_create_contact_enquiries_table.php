<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::create('contact_enquiries',function(Blueprint $table){$table->id();$table->string('reference')->unique();$table->json('payload');$table->string('status')->default('new');$table->timestamps();}); }
    public function down():void { Schema::dropIfExists('contact_enquiries'); }
};
