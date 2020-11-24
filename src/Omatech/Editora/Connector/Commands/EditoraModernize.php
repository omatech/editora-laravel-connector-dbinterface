<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;


class EditoraModernize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editora:modernize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Modernize editora database';

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
        $command = base_path('/vendor/omatech/editora-dbinterface/Commands/modernize.php');

        $this->line(shell_exec('php '.$command.' --to=db4 --dbhost='.env('DB_HOST').' --dbuser='.env('DB_USERNAME').' --dbpass='.env('DB_PASSWORD').' --dbname='.env('DB_DATABASE').''));
    }

}



