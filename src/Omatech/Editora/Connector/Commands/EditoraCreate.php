<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;


class EditoraCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editora:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create editora database';

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
        $command = base_path('/vendor/omatech/editora-dbinterface/generate-editora.php');
        $arraydatabase = config_path('editoradatabase.php');

        $this->line(shell_exec('php '.$command.' --from=file --inputformat=array --inputfile='.$arraydatabase.' --to=db4 --dbtohost='.env('DB_HOST').' --dbtouser='.env('DB_USERNAME').' --dbtopass='.env('DB_PASSWORD').' --dbtoname='.env('DB_DATABASE').''));
    }

}



