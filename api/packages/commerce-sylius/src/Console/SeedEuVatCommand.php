<?php

namespace Ulams\CommerceSylius\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\CommerceSylius\EuVatSeeder;
use Ulams\CommerceSylius\Http\SyliusClient;

/** Seeds EU VAT into the Sylius service (platform level, once per installation, safe to repeat). */
class SeedEuVatCommand extends Command
{
    protected $signature = 'ulams:commerce:seed-eu-vat';

    protected $description = 'Create the EU country zones, the e-service tax category and the standard VAT rates in Sylius';

    public function handle(): int
    {
        $client = SyliusClient::fromConfig();
        if (!$client->configured()) {
            $this->error('The Sylius service is not configured (COMMERCE_SYLIUS_URL and the admin API user).');

            return self::FAILURE;
        }
        $seeder = new EuVatSeeder($client);
        try {
            $meta = $seeder->data();
            $result = $seeder->seed();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("EU VAT: {$result['created']} created, {$result['updated']} updated, {$result['unchanged']} unchanged.");
        $this->line("Rates as of {$meta['as_of']} ({$meta['source']})" . ($meta['confirmed'] ? '.' : ': UNCONFIRMED. Have the seller\'s accountant check every rate before the first live sale.'));

        return self::SUCCESS;
    }
}
