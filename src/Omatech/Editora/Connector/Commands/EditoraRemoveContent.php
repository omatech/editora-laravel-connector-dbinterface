<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EditoraRemoveContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editora:removecontent {--batch_id=} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete content whith batch_id to editora database';

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
            $arguments = '';
            if(!empty($this->option('batch_id') )){
                $arguments .= " --batch_id=".$this->option('batch_id')." ";

                $command = base_path('/vendor/omatech/editora-dbinterface/Commands/remove-content.php');
                $line='php '.$command.' --to=db4 '.$arguments.' --dbhost='.env('DB_HOST').' --dbuser='.env('DB_USERNAME').' --dbpass='.env('DB_PASSWORD').' --dbname='.env('DB_DATABASE');
                echo "Running command: $line\n";
                $this->line(shell_exec($line));

            }

        }else{
            print_r('Function only local environment');
        }

    }

}



