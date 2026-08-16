<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Enums\Cambio;
use App\Enums\Combustivel;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('placa', length: 7)->unique();
            $table->string('chassi', length: 17)->unique();
            $table->string('marca');
            $table->string('modelo');
            $table->string('versao');
            $table->decimal('valor_venda', total: 15, places: 2);
            $table->string('cor');
            $table->integer('km');
            $table->enum('cambio', Cambio::cases());
            $table->enum('combustivel', Combustivel::cases());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle');
    }
};
