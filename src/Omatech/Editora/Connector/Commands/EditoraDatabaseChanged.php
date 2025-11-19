<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class EditoraDatabaseChanged extends Command
{
    protected $signature = 'editora:databasechanged';
    protected $description = 'Execute editora:create only if config/editoradatabase.php changed since last deploy';

    public function __construct() {
		parent::__construct();
	}

    public function handle()
    {
        $configPath = config_path('editoradatabase.php');
        $currentHash = md5_file($configPath);

        $cacheKey = 'editoradatabase_hash';

        $previousHash = Cache::get($cacheKey);

        if ($currentHash !== $previousHash) {
            $this->info("Detected change in editoradatabase.php → running editora:create");

            Artisan::call('editora:create');
            $this->line(Artisan::output());

        } else {
            $this->info("No changes detected in editoradatabase.php → skipping editora:create");
        }

        Cache::forever($cacheKey, $currentHash);

        return 0;
    }
}
