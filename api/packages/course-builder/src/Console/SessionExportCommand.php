<?php

namespace Ulams\CourseBuilder\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Transfer\SessionArchive;

class SessionExportCommand extends Command
{
    protected $signature = 'course-builder:session:export {session : Session id} {path : Archive to write (.tar)}';

    protected $description = 'Write a builder session (brief, current and applied versions, sources and fragments) to a tar archive';

    public function handle(SessionArchive $archive): int
    {
        $session = Session::query()->find($this->argument('session'));
        if ($session === null) {
            $this->error('No such session.');

            return self::FAILURE;
        }
        try {
            $archive->export($session, (string) $this->argument('path'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Exported ' . $session->id . ' to ' . $this->argument('path'));

        return self::SUCCESS;
    }
}
