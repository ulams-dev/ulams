<?php

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ulams\Identity\Services\Scim\FilterParser;
use Ulams\Identity\Services\Scim\ScimError;

class ScimFilterParserTest extends TestCase
{
    private function parse(string $filter): array
    {
        return (new FilterParser())->parse($filter);
    }

    /** @return array<string,array{0:string,1:array<string,mixed>}> */
    public static function grammar(): array
    {
        return [
            'eq string' => ['userName eq "bjensen"', ['t' => 'cmp', 'attr' => 'userName', 'op' => 'eq', 'value' => 'bjensen']],
            'operators are case-insensitive' => ['userName EQ "x"', ['t' => 'cmp', 'attr' => 'userName', 'op' => 'eq', 'value' => 'x']],
            'ne' => ['userName ne "x"', ['t' => 'cmp', 'attr' => 'userName', 'op' => 'ne', 'value' => 'x']],
            'co' => ['name.familyName co "O\'Malley"', ['t' => 'cmp', 'attr' => 'name.familyName', 'op' => 'co', 'value' => "O'Malley"]],
            'sw' => ['userName sw "J"', ['t' => 'cmp', 'attr' => 'userName', 'op' => 'sw', 'value' => 'J']],
            'ew' => ['emails.value ew "example.com"', ['t' => 'cmp', 'attr' => 'emails.value', 'op' => 'ew', 'value' => 'example.com']],
            'pr' => ['title pr', ['t' => 'pr', 'attr' => 'title']],
            'gt date' => ['meta.lastModified gt "2011-05-13T04:42:34Z"', ['t' => 'cmp', 'attr' => 'meta.lastModified', 'op' => 'gt', 'value' => '2011-05-13T04:42:34Z']],
            'ge' => ['meta.lastModified ge "2011-05-13T04:42:34Z"', ['t' => 'cmp', 'attr' => 'meta.lastModified', 'op' => 'ge', 'value' => '2011-05-13T04:42:34Z']],
            'lt' => ['meta.lastModified lt "2011-05-13T04:42:34Z"', ['t' => 'cmp', 'attr' => 'meta.lastModified', 'op' => 'lt', 'value' => '2011-05-13T04:42:34Z']],
            'le' => ['meta.lastModified le "2011-05-13T04:42:34Z"', ['t' => 'cmp', 'attr' => 'meta.lastModified', 'op' => 'le', 'value' => '2011-05-13T04:42:34Z']],
            'true' => ['active eq true', ['t' => 'cmp', 'attr' => 'active', 'op' => 'eq', 'value' => true]],
            'false' => ['active eq false', ['t' => 'cmp', 'attr' => 'active', 'op' => 'eq', 'value' => false]],
            'null' => ['externalId eq null', ['t' => 'cmp', 'attr' => 'externalId', 'op' => 'eq', 'value' => null]],
            'number' => ['age gt 21', ['t' => 'cmp', 'attr' => 'age', 'op' => 'gt', 'value' => 21]],
            'escapes' => ['userName eq "a\\"b\\\\c\\u00e9"', ['t' => 'cmp', 'attr' => 'userName', 'op' => 'eq', 'value' => 'a"b\\cé']],
            'enterprise urn attribute' => ['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:department eq "ICU"', ['t' => 'cmp', 'attr' => 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:department', 'op' => 'eq', 'value' => 'ICU']],
            'value path' => ['emails[type eq "work"]', ['t' => 'path', 'attr' => 'emails', 'filter' => ['t' => 'cmp', 'attr' => 'type', 'op' => 'eq', 'value' => 'work']]],
            'value path with and' => ['emails[type eq "work" and value co "@example.com"]', ['t' => 'path', 'attr' => 'emails', 'filter' => ['t' => 'and',
                'l' => ['t' => 'cmp', 'attr' => 'type', 'op' => 'eq', 'value' => 'work'], 'r' => ['t' => 'cmp', 'attr' => 'value', 'op' => 'co', 'value' => '@example.com']]]],
            'members value path' => ['members[value eq "42"]', ['t' => 'path', 'attr' => 'members', 'filter' => ['t' => 'cmp', 'attr' => 'value', 'op' => 'eq', 'value' => '42']]],
            'not' => ['not (userName eq "x")', ['t' => 'not', 'n' => ['t' => 'cmp', 'attr' => 'userName', 'op' => 'eq', 'value' => 'x']]],
        ];
    }

    #[DataProvider('grammar')]
    public function testTheGrammarOfRfc7644(string $filter, array $expected): void
    {
        $this->assertSame($expected, $this->parse($filter));
    }

    public function testAndBindsTighterThanOr(): void
    {
        $tree = $this->parse('a eq "1" or b eq "2" and c eq "3"');

        $this->assertSame('or', $tree['t']);
        $this->assertSame('cmp', $tree['l']['t']);
        $this->assertSame('and', $tree['r']['t']);
    }

    public function testParenthesesOverridePrecedence(): void
    {
        $tree = $this->parse('(a eq "1" or b eq "2") and c eq "3"');

        $this->assertSame('and', $tree['t']);
        $this->assertSame('or', $tree['l']['t']);
    }

    public function testNotAppliesToTheParenthesisedFilter(): void
    {
        $tree = $this->parse('not (a eq "1" and b eq "2") or c pr');

        $this->assertSame('or', $tree['t']);
        $this->assertSame('not', $tree['l']['t']);
        $this->assertSame('and', $tree['l']['n']['t']);
    }

    /** @return array<string,array{0:string}> */
    public static function invalid(): array
    {
        return [
            'empty' => [''], 'blank' => ['   '], 'no operator' => ['userName'], 'no value' => ['userName eq'], 'bad operator' => ['userName like "x"'],
            'unclosed string' => ['userName eq "x'], 'unclosed paren' => ['(userName eq "x"'], 'extra paren' => ['userName eq "x")'], 'dangling and' => ['userName eq "x" and'],
            'unclosed bracket' => ['emails[type eq "work"'], 'not without parens' => ['not userName eq "x"'], 'bare value' => ['"x" eq "y"'],
            'two values' => ['userName eq "x" "y"'], 'sql' => ['userName eq "x"; DROP TABLE users'], 'bad escape' => ['userName eq "\\x"'],
            'pr with value' => ['title pr "x"'], 'operator as value' => ['userName eq eq'], 'control character' => ["userName eq \"x\"\x01"],
            'empty value path' => ['emails[]'], 'bad utf8' => ["userName eq \"\xff\""],
        ];
    }

    #[DataProvider('invalid')]
    public function testInvalidFiltersAreAnInvalidFilterError(string $filter): void
    {
        try {
            $this->parse($filter);
            $this->fail('Expected a ScimError for: ' . json_encode($filter));
        } catch (ScimError $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('invalidFilter', $e->scimType);
        }
    }

    public function testALongFilterAndDeepNestingAreRefused(): void
    {
        $this->expectException(ScimError::class);
        $this->parse('userName eq "' . str_repeat('a', 1000) . '"');
    }

    public function testDeepNestingIsRefused(): void
    {
        $this->expectException(ScimError::class);
        $this->parse(str_repeat('(', 40) . 'a pr' . str_repeat(')', 40));
    }

    public function testRandomInputNeverThrowsAnythingButInvalidFilter(): void
    {
        mt_srand(7);
        $pieces = ['userName', 'eq', 'ne', 'co', 'sw', 'ew', 'pr', 'gt', 'and', 'or', 'not', '(', ')', '[', ']', '"x"', '"', '\\', 'true', 'null', '1', '-', '.', ':', ' ', 'emails', "\t", '$', '%', "'", ';', "\x00", "\xc3"];
        for ($i = 0; $i < 3000; $i++) {
            $input = '';
            for ($n = mt_rand(1, 12); $n > 0; $n--) {
                $input .= $pieces[mt_rand(0, count($pieces) - 1)] . (mt_rand(0, 2) === 0 ? '' : ' ');
            }
            try {
                $this->parse($input);
            } catch (ScimError $e) {
                $this->assertSame('invalidFilter', $e->scimType, $input);
            }
        }
        for ($i = 0; $i < 500; $i++) {
            try {
                $this->parse(random_bytes(mt_rand(1, 60)));
            } catch (ScimError $e) {
                $this->assertSame('invalidFilter', $e->scimType);
            }
        }
        $this->addToAssertionCount(1);
    }
}
