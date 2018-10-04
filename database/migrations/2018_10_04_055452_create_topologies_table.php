<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTopologiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(config('topology.audit_store'))->
                create('topologies', function (Blueprint $table) {
                    $table->string('table');
                    $table->string('topology');
                    $table->unsignedBigInteger('count');

                    $table->primary(['table', 'topology']);
                });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('topology.audit_store'))->
                dropIfExists('topologies');
    }
}
