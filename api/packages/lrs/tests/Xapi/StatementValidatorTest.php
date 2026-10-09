<?php

namespace Ulams\Lrs\Tests\Xapi;

use PHPUnit\Framework\TestCase;
use Ulams\Lrs\Xapi\Agent;
use Ulams\Lrs\Xapi\StatementValidator;
use Ulams\Lrs\Xapi\XapiException;
use PHPUnit\Framework\Attributes\DataProvider;

class StatementValidatorTest extends TestCase
{
    private function valid(): array
    {
        return [
            'id' => '6690e6c9-3ef0-4ed3-8b37-7f3964730bee',
            'actor' => ['objectType' => 'Agent', 'name' => 'Learner', 'account' => ['homePage' => 'https://ulams.app', 'name' => 'learner@example.com']],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed', 'display' => ['en-US' => 'completed']],
            'object' => ['id' => 'https://example.com/activities/1', 'definition' => ['name' => ['en-US' => 'One']]],
            'result' => ['score' => ['scaled' => 0.8, 'raw' => 8, 'min' => 0, 'max' => 10], 'success' => true, 'completion' => true, 'duration' => 'PT1H2M3.5S'],
            'context' => [
                'registration' => 'ec531277-b57b-4c15-8d91-d292c5b2b8f7',
                'contextActivities' => ['category' => [['id' => 'https://w3id.org/xapi/cmi5/context/categories/cmi5']]],
                'extensions' => ['https://w3id.org/xapi/cmi5/context/extensions/sessionid' => 'abc'],
            ],
            'timestamp' => '2026-10-08T12:00:00.000Z',
        ];
    }

    public function testValidStatementsPass(): void
    {
        $this->assertSame([], StatementValidator::errors($this->valid()));

        $minimal = ['actor' => ['mbox' => 'mailto:a@b.c'], 'verb' => ['id' => 'urn:verb:x'], 'object' => ['id' => 'urn:activity:x']];
        $this->assertSame([], StatementValidator::errors($minimal));

        $voiding = [
            'actor' => ['mbox' => 'mailto:a@b.c'],
            'verb' => ['id' => StatementValidator::VOIDED_VERB],
            'object' => ['objectType' => 'StatementRef', 'id' => '6690e6c9-3ef0-4ed3-8b37-7f3964730bee'],
        ];
        $this->assertSame([], StatementValidator::errors($voiding));

        $sub = $minimal;
        $sub['object'] = ['objectType' => 'SubStatement'] + $minimal;
        $this->assertSame([], StatementValidator::errors($sub));

        $group = $minimal;
        $group['actor'] = ['objectType' => 'Group', 'member' => [['mbox' => 'mailto:x@y.z']]];
        $this->assertSame([], StatementValidator::errors($group));
    }

    public static function invalidStatements(): array
    {
        $cases = [
            'not an object' => fn () => 'string',
            'missing actor' => fn (array $s) => array_diff_key($s, ['actor' => 1]),
            'missing verb' => fn (array $s) => array_diff_key($s, ['verb' => 1]),
            'missing object' => fn (array $s) => array_diff_key($s, ['object' => 1]),
            'bad id' => fn (array $s) => ['id' => 'not-a-uuid'] + $s,
            'agent without identifier' => fn (array $s) => ['actor' => ['name' => 'x']] + $s,
            'agent with two identifiers' => fn (array $s) => ['actor' => ['mbox' => 'mailto:a@b.c', 'openid' => 'https://x']] + $s,
            'bad mbox' => fn (array $s) => ['actor' => ['mbox' => 'a@b.c']] + $s,
            'verb id not IRI' => fn (array $s) => ['verb' => ['id' => 'completed']] + $s,
            'object id not IRI' => fn (array $s) => ['object' => ['id' => 'nope']] + $s,
            'unknown property' => fn (array $s) => $s + ['foo' => 'bar'],
            'scaled out of range' => fn (array $s) => array_replace_recursive($s, ['result' => ['score' => ['scaled' => 1.5]]]),
            'raw above max' => fn (array $s) => array_replace_recursive($s, ['result' => ['score' => ['raw' => 11]]]),
            'bad duration' => fn (array $s) => array_replace_recursive($s, ['result' => ['duration' => '1 hour']]),
            'success not boolean' => fn (array $s) => array_replace_recursive($s, ['result' => ['success' => 'yes']]),
            'bad registration' => fn (array $s) => array_replace_recursive($s, ['context' => ['registration' => '123']]),
            'bad timestamp' => fn (array $s) => ['timestamp' => 'yesterday'] + $s,
            'bad version' => fn (array $s) => $s + ['version' => '2.0.0'],
            'attachments' => fn (array $s) => $s + ['attachments' => []],
            'voiding without StatementRef' => fn (array $s) => ['verb' => ['id' => StatementValidator::VOIDED_VERB]] + $s,
            'nested SubStatement' => fn (array $s) => ['object' => ['objectType' => 'SubStatement', 'actor' => $s['actor'], 'verb' => $s['verb'], 'object' => ['objectType' => 'SubStatement']]] + $s,
        ];

        return array_map(fn ($case) => [$case], $cases);
    }

    #[DataProvider('invalidStatements')]
    public function testInvalidStatementsFail(callable $mutate): void
    {
        $this->assertNotSame([], StatementValidator::errors($mutate($this->valid())));
    }

    public function testAgentVirtualIds(): void
    {
        $this->assertSame('mbox::mailto:a@b.c', Agent::virtualId(['mbox' => 'mailto:a@b.c']));
        $this->assertSame('account::jane@https://ulams.app', Agent::virtualId(['account' => ['homePage' => 'https://ulams.app', 'name' => 'jane']]));
        $this->assertSame(['openid' => 'https://id.example.com/j'], Agent::ifi(['name' => 'J', 'openid' => 'https://id.example.com/j']));
    }

    public function testAgentParameterMustBeAValidAgent(): void
    {
        $this->assertSame(['mbox' => 'mailto:a@b.c'], Agent::fromParameter('{"mbox":"mailto:a@b.c"}'));

        foreach ([null, '', 'not json', '[1]', '{"name":"no id"}'] as $value) {
            try {
                Agent::fromParameter($value);
                $this->fail('Accepted agent parameter: ' . var_export($value, true));
            } catch (XapiException $e) {
                $this->assertSame(400, $e->status());
            }
        }
    }
}
