<?php

namespace Pete\PeteApi;

use Illuminate\Support\ServiceProvider;

class PeteApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('pete-api-plugin', function ($app) {
            return new PeteApi;
        });
    }

    public function boot()
    {
        // loading the routes file
       // require __DIR__ . '/Http/routes.php';
		
		//define the path for the view files
		$this->loadRoutesFrom(__DIR__.'/routes/web.php');
		$this->loadViewsFrom(__DIR__.'/views','pete-api-plugin');
		
		//define files which are going to publish
		//$this->publishes([__DIR__.'/migrations/2020_05_000000_create_todo_table.php' => base_path('database/migrations/2020_05_000000_create_to_table.php')]);
		
		//$this->publishes([__DIR__.'/scripts/mac_wordpress_laravel.sh' => base_path('scripts/mac_wordpress_laravel.sh')]);
		
    }
}
