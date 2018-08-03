<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;


class EditoraFakeContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editora:fakecontent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create fake content to editora database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if(strcmp(env('APP_ENV'), 'local') == 0) {

            $command = base_path('/vendor/omatech/editora-dbinterface/fake-content.php');
            $this->line(shell_exec('php '.$command.' --to=db4 --dbhost='.env('DB_CONNECTION').' --dbuser='.env('DB_USERNAME').' --dbpass='.env('DB_PASSWORD').' --dbname='.env('DB_DATABASE').''));

        }else{
            print_r('Function only local environment');
        }

    }

}



