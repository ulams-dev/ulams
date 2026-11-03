<?php

namespace Ulams\Reporting\OData;

use XMLWriter;

/**
 * The `$metadata` document of the feed (OData v4 CSDL, XML): one entity set per report, whose entity type has a
 * `RowNumber` key (a report has no natural key) and one property per output column. The Capabilities annotations tell
 * clients such as Power BI that nothing but `$select`, `$top`, `$skip` and `$count` is supported, so they do not fold a
 * `$filter` into the request.
 */
final class Csdl
{
    public const NAMESPACE = 'Ulams.Reporting';

    private const TYPES = ['string' => 'Edm.String', 'enum' => 'Edm.String', 'int' => 'Edm.Int64', 'decimal' => 'Edm.Double', 'bool' => 'Edm.Boolean', 'date' => 'Edm.Date', 'datetime' => 'Edm.DateTimeOffset'];

    public static function edmType(string $type): string
    {
        return self::TYPES[$type] ?? 'Edm.String';
    }

    public static function entitySet(string $id): string
    {
        return 'Report_' . $id;
    }

    /**
     * @param array<string,list<array{name:string,type:string,label:string}>> $reports entity set name => output columns
     * @param array<string,bool> $grouped entity set name => whether rows can be withheld (adds the `Withheld` flag)
     */
    public static function document(array $reports, array $grouped = []): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs('edmx', 'Edmx', 'http://docs.oasis-open.org/odata/ns/edmx');
        $xml->writeAttribute('Version', '4.0');
        $xml->startElementNs('edmx', 'Reference', null);
        $xml->writeAttribute('Uri', 'https://oasis-tcs.github.io/odata-vocabularies/vocabularies/Org.OData.Capabilities.V1.xml');
        $xml->startElementNs('edmx', 'Include', null);
        $xml->writeAttribute('Namespace', 'Org.OData.Capabilities.V1');
        $xml->writeAttribute('Alias', 'Capabilities');
        $xml->endElement();
        $xml->endElement();
        $xml->startElementNs('edmx', 'Reference', null);
        $xml->writeAttribute('Uri', 'https://oasis-tcs.github.io/odata-vocabularies/vocabularies/Org.OData.Core.V1.xml');
        $xml->startElementNs('edmx', 'Include', null);
        $xml->writeAttribute('Namespace', 'Org.OData.Core.V1');
        $xml->writeAttribute('Alias', 'Core');
        $xml->endElement();
        $xml->endElement();
        $xml->startElementNs('edmx', 'DataServices', null);
        $xml->startElement('Schema');
        $xml->writeAttribute('xmlns', 'http://docs.oasis-open.org/odata/ns/edm');
        $xml->writeAttribute('Namespace', self::NAMESPACE);

        foreach ($reports as $set => $columns) {
            $xml->startElement('EntityType');
            $xml->writeAttribute('Name', $set . '_Row');
            $xml->startElement('Key');
            $xml->startElement('PropertyRef');
            $xml->writeAttribute('Name', 'RowNumber');
            $xml->endElement();
            $xml->endElement();
            self::property($xml, 'RowNumber', 'Edm.Int64', false);
            foreach ($columns as $column) {
                self::property($xml, $column['name'], self::edmType($column['type']), true, $column['label']);
            }
            if ($grouped[$set] ?? false) {
                self::property($xml, 'Withheld', 'Edm.Boolean', true, 'Withheld: this row stands for groups held back to protect small groups');
            }
            $xml->endElement();
        }

        $xml->startElement('EntityContainer');
        $xml->writeAttribute('Name', 'Container');
        foreach (array_keys($reports) as $set) {
            $xml->startElement('EntitySet');
            $xml->writeAttribute('Name', $set);
            $xml->writeAttribute('EntityType', self::NAMESPACE . '.' . $set . '_Row');
            foreach ([
                'FilterRestrictions' => ['Filterable' => 'false'],
                'SortRestrictions' => ['Sortable' => 'false'],
                'ExpandRestrictions' => ['Expandable' => 'false'],
                'CountRestrictions' => ['Countable' => 'true'],
                'SearchRestrictions' => ['Searchable' => 'false'],
                'InsertRestrictions' => ['Insertable' => 'false'],
                'UpdateRestrictions' => ['Updatable' => 'false'],
                'DeleteRestrictions' => ['Deletable' => 'false'],
            ] as $term => $values) {
                $xml->startElement('Annotation');
                $xml->writeAttribute('Term', 'Org.OData.Capabilities.V1.' . $term);
                $xml->startElement('Record');
                foreach ($values as $name => $bool) {
                    $xml->startElement('PropertyValue');
                    $xml->writeAttribute('Property', $name);
                    $xml->writeAttribute('Bool', $bool);
                    $xml->endElement();
                }
                $xml->endElement();
                $xml->endElement();
            }
            foreach (['TopSupported', 'SkipSupported'] as $term) {
                $xml->startElement('Annotation');
                $xml->writeAttribute('Term', 'Org.OData.Capabilities.V1.' . $term);
                $xml->writeAttribute('Bool', 'true');
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement(); // EntityContainer
        $xml->endElement(); // Schema
        $xml->endElement(); // DataServices
        $xml->endElement(); // Edmx
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private static function property(XMLWriter $xml, string $name, string $type, bool $nullable, ?string $label = null): void
    {
        $xml->startElement('Property');
        $xml->writeAttribute('Name', $name);
        $xml->writeAttribute('Type', $type);
        if (!$nullable) {
            $xml->writeAttribute('Nullable', 'false');
        }
        if ($label !== null) {
            $xml->startElement('Annotation');
            $xml->writeAttribute('Term', 'Org.OData.Core.V1.Description');
            $xml->writeAttribute('String', $label);
            $xml->endElement();
        }
        $xml->endElement();
    }
}
