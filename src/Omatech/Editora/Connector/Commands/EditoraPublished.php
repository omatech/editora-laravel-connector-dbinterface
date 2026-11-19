<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use LogicException;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Throwable;

/**
 * Records a publication, as an Editora admin save does: content and pages cached before it stop being served, and the site
 * warms again in the background once the command ends. Run it after anything that writes what public pages show outside
 * the Editora admin, such as an indexer or an import; otherwise the page cache keeps showing the previous rows for up to
 * its TTL (a week).
 */
final class EditoraPublished extends Command
{
    protected $signature = 'editora:published';

    protected $description = 'Record a publication, so pages cached before it are rendered again and the site warms';

    public function handle(ContentFreshness $freshness): int
    {
        try {
            $freshness->published();
        } catch (LogicException) {
            $this->error('Publications are not tracked: register ContentFreshness (README, "Publications").');

            return self::FAILURE;
        } catch (Throwable $e) {
            report($e);
            $this->error('The publication could not be recorded: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Publication recorded: the site warms in the background.');

        return self::SUCCESS;
    }
}
