<?php

namespace Ulams\CommerceSylius;

use RuntimeException;
use Ulams\CommerceSylius\Http\SyliusClient;

/**
 * Sets up Sylius for EU VAT on digital services (ADR 0131, plan section 11): the 27 member states as enabled countries,
 * one country zone each (`eu-<cc>`), one `e-service` tax category and one tax rate per country (`vat-<cc>`, included in
 * the price). Sylius picks the zone from the billing address and falls back to the channel's default tax zone. The rates
 * come from `resources/eu-vat-rates.json`, which is seed data the seller's accountant must confirm.
 *
 * Idempotent: it creates what is missing, corrects a rate that differs from the file and enables a disabled country; it
 * never deletes anything. It does not file or collect OSS evidence: the OSS return stays manual (plan section 11).
 */
class EuVatSeeder
{
    private const ADMIN = '/api/v2/admin';

    public function __construct(private readonly SyliusClient $client, private readonly ?string $file = null)
    {
    }

    /** @return array<string,mixed> the file: note, as_of, confirmed, source and rates (percent by country code) */
    public function data(): array
    {
        $data = json_decode((string) file_get_contents($this->file ?? __DIR__ . '/../resources/eu-vat-rates.json'), true);
        if (!is_array($data) || !is_array($data['rates'] ?? null) || count($data['rates']) !== 27) {
            throw new RuntimeException('The EU VAT rates file must hold the rates of exactly 27 member states.');
        }

        return $data;
    }

    /** @return list<string> */
    public function countries(): array
    {
        return array_keys($this->data()['rates']);
    }

    /** @return array{created:int,updated:int,unchanged:int} counts of countries, zones and rates together */
    public function seed(): array
    {
        $category = (string) config('commerce-sylius.tax_category', 'e-service');
        $count = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $tally = function (string $what) use (&$count): void {
            ++$count[$what];
        };

        if ($this->client->get(self::ADMIN . '/tax-categories/' . $category) === null) {
            $this->client->post(self::ADMIN . '/tax-categories', ['code' => $category, 'name' => 'Electronic service']);
            $tally('created');
        } else {
            $tally('unchanged');
        }

        foreach ($this->data()['rates'] as $country => $percent) {
            $country = (string) $country;
            $lower = strtolower($country);

            $existing = $this->client->get(self::ADMIN . '/countries/' . $country);
            if ($existing === null) {
                $this->client->post(self::ADMIN . '/countries', ['code' => $country, 'enabled' => true]);
                $tally('created');
            } elseif (($existing['enabled'] ?? false) !== true) {
                $this->client->put(self::ADMIN . '/countries/' . $country, ['enabled' => true]);
                $tally('updated');
            } else {
                $tally('unchanged');
            }

            if ($this->client->get(self::ADMIN . "/zones/eu-{$lower}") === null) {
                $this->client->post(self::ADMIN . '/zones', ['code' => "eu-{$lower}", 'name' => "EU: {$country}", 'type' => 'country', 'scope' => 'all', 'members' => [['code' => $country]]]);
                $tally('created');
            } else {
                $tally('unchanged');
            }

            $amount = round(((float) $percent) / 100, 4);
            $rate = $this->client->get(self::ADMIN . "/tax-rates/vat-{$lower}");
            if ($rate === null) {
                $this->client->post(self::ADMIN . '/tax-rates', [
                    'code' => "vat-{$lower}", 'name' => "VAT {$country}", 'amount' => $amount, 'includedInPrice' => true, 'calculator' => 'default',
                    'zone' => self::ADMIN . "/zones/eu-{$lower}", 'category' => self::ADMIN . "/tax-categories/{$category}",
                ]);
                $tally('created');
            } elseif (abs((float) ($rate['amount'] ?? -1) - $amount) > 0.00001) {
                $this->client->put(self::ADMIN . "/tax-rates/vat-{$lower}", ['amount' => $amount]);
                $tally('updated');
            } else {
                $tally('unchanged');
            }
        }

        return $count;
    }
}
