<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('songs', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('bpm')->nullable()->index()->after('year');
            $table->string('musical_key', 8)->nullable()->index()->after('bpm');
        });
    }
};
