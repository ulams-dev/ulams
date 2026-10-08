<?php


namespace EscolaLms\Categories\Commands;

use EscolaLms\Categories\Enums\CategoriesPermissionsEnum;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class CategoriesSeedCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'category-permissions:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with the permissions required to support the categories';

    public function handle() : void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::findOrCreate(CategoriesPermissionsEnum::CATEGORY_UPDATE, 'api');
        Permission::findOrCreate(CategoriesPermissionsEnum::CATEGORY_DELETE, 'api');
        Permission::findOrCreate(CategoriesPermissionsEnum::CATEGORY_CREATE, 'api');
    }
}
