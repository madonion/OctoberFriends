<?php namespace DMA\Friends\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreatePivotTables extends Migration
{

    public function up()
    {   
        Schema::create('dma_friends_activity_step', function($table)
        {   
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();
            $table->integer('activity_id');
            $table->integer('step_id');

            $table->index('activity_id');
            $table->index('step_id');
        }); 

        Schema::create('dma_friends_step_badge', function($table)
        {   
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();
            $table->integer('step_id');
            $table->integer('badge_id');

            $table->index('step_id');
            $table->index('badge_id');
        }); 

        Schema::create('dma_friends_location_badge', function($table)
        {   
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();
            $table->integer('location_id');
            $table->integer('badge_id');

            $table->index('location_id');
            $table->index('badge_id');
        }); 
    }   

    public function down()
    {   
        Schema::dropIfExists('dma_friends_activity_step');
        Schema::dropIfExists('dma_friends_step_badge');
        Schema::dropIfExists('dma_friends_location_badge');
    }   

}
