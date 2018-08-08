<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EditoraFakeContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editora:fakecontent {--num_instances=} {--include_classes=} {--exclude_classes=} {--pictures_theme=} {--debug=}';

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
            $arguments = '';
            if(!empty($this->option('num_instances') )){
                $arguments .= " --num_instances=".$this->option('num_instances')." ";
            }
            if(!empty($this->option('include_classes'))){
                $arguments .= " --include_classes=".$this->option('include_classes')." ";
            }
            if(!empty($this->option('exclude_classes'))){
                $arguments .= " --exclude_classes=".$this->option('exclude_classes')." ";
            }
            if(!empty($this->option('pictures_theme'))){
                $arguments .= " --pictures_theme=".$this->option('pictures_theme')." ";
            }
            if(!empty($this->option('debug'))){
                $arguments .= " --debug=".$this->option('debug')." ";
            }else{
                $arguments .= " --debug=false ";
            }

            $command = base_path('/vendor/omatech/editora-dbinterface/Commands/fake-content.php');
            $this->line(shell_exec('php '.$command.' --to=db4 --dbhost='.env('DB_CONNECTION').' --dbuser='.env('DB_USERNAME').' --dbpass='.env('DB_PASSWORD').' --dbname='.env('DB_DATABASE').' '.$arguments));

        }else{
            print_r('Function only local environment');
        }

    }

}



