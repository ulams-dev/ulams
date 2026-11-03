<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ulams\Tenancy\Services\TenantTemplates;
use Ulams\Tenancy\Support\TenantContext;

class TemplateCommand extends Command
{
    protected $signature = 'ulams:tenant:template
        {slug : 2-39 lowercase letters, digits and hyphens, starting with a letter}
        {--name= : Display name}
        {--theme= : Front theme preset key}
        {--accent= : Accent colour, e.g. #C2552D}
        {--demo= : Demo mode for tenants made from it: on or off}
        {--users= : Demo students created with a tenant made from it (0-50)}
        {--set=* : KEY=value of an inheritable setting (AI driver, models), may be repeated}
        {--delete : Remove the template (tenants made from it are untouched)}';

    protected $description = 'Create, change or remove a tenant template: the starting point of new tenants (ulams:tenant:create --template).';

    public function handle(TenantTemplates $templates): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }
        $slug = (string) $this->argument('slug');
        if ($this->option('delete')) {
            $this->line($templates->delete($slug) ? "Removed template {$slug}." : "There is no template {$slug}.");

            return self::SUCCESS;
        }
        try {
            $env = [];
            foreach ((array) $this->option('set') as $pair) {
                [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
                $env[$key] = $value;
            }
            $template = $templates->save($slug, array_filter([
                'name' => $this->option('name'),
                'theme' => $this->option('theme'),
                'accent' => $this->option('accent'),
                'demo' => $this->option('demo') === null ? null : $this->option('demo') === 'on',
                'users' => $this->option('users') === null ? null : (int) $this->option('users'),
                'env' => $env ?: null,
            ], fn ($value) => $value !== null));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->table(['Slug', 'Name', 'Theme', 'Accent', 'Demo', 'Users', 'Settings'], [[$template->slug, $template->name, $template->theme ?? '-', $template->accent ?? '-', $template->demo ? 'on' : 'off', $template->users, implode(', ', array_keys((array) $template->env_overrides)) ?: '-']]);

        return self::SUCCESS;
    }
}
