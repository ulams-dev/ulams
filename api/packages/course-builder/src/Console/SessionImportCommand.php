<?php

namespace Ulams\CourseBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Ulams\CourseBuilder\Transfer\SessionArchive;

class SessionImportCommand extends Command
{
    protected $signature = 'course-builder:session:import
        {path : Archive written by course-builder:session:export}
        {--author-email= : The author of the new session; created as an admin with an invitation when missing}
        {--author-name= : Display name for a new author}';

    protected $description = 'Create a builder session from an archive for an author of this tenant. The last output line is JSON with the new session id.';

    public function handle(SessionArchive $archive): int
    {
        $email = strtolower(trim((string) $this->option('author-email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give the author with --author-email.');

            return self::FAILURE;
        }
        $model = config('auth.providers.users.model');
        $author = $model::query()->where('email', $email)->first();
        $invited = false;
        if ($author === null) {
            $name = trim((string) $this->option('author-name')) ?: Str::before($email, '@');
            $author = new $model(['email' => $email, 'first_name' => Str::before($name, ' '), 'last_name' => Str::after($name, ' ') === $name ? '' : Str::after($name, ' '), 'password' => bcrypt(Str::random(40)), 'is_active' => true]);
            $author->email_verified_at = now();
            $author->save();
            $author->syncRoles(['admin']);
            try {
                // the invitation is a password-reset link: only where the app has the reset table
                $invited = Schema::hasTable((string) config('auth.passwords.users.table', 'password_resets')) && Password::broker()->sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
            } catch (Throwable) {
                $invited = false;
            }
        }
        try {
            $session = $archive->import((string) $this->argument('path'), (int) $author->getKey());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode(['sessionId' => $session->id, 'authorId' => $author->getKey(), 'invited' => $invited]));

        return self::SUCCESS;
    }
}
