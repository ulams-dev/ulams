<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Http\Request;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Support\TokenContext;
use Ulams\CourseBuilder\CourseAsCode\ApprovalGuard;
use Ulams\CourseBuilder\CourseAsCode\CourseAsCodeException;
use Ulams\CourseBuilder\Tests\TestCase;

/** Who may approve an imported version (ADR 0160): people and CLI tokens; never agents or MCP clients. */
class ApprovalGuardTest extends TestCase
{
    public function testEveryApprovalRuleByTokenKind(): void
    {
        $user = $this->author();
        $approves = function (?string $kind, array $headers = []) use ($user): bool {
            $request = Request::create('/x', 'POST');
            foreach ($headers as $name => $value) {
                $request->headers->set($name, $value);
            }
            if ($kind !== null) {
                $request->attributes->set('ulams.token_context', new TokenContext('tok', ['builder:write'], new ApiTokenMeta(['kind' => $kind]), $user));
            }
            try {
                app(ApprovalGuard::class)->assertMayApprove($request);

                return true;
            } catch (CourseAsCodeException $e) {
                $this->assertSame('APPROVAL_REQUIRES_HUMAN', $e->errorCode);
                $this->assertSame(403, $e->status);

                return false;
            }
        };
        $this->assertTrue($approves(null), 'a studio login');
        $this->assertTrue($approves('cli'));
        $this->assertFalse($approves('agent'));
        $this->assertFalse($approves('integration'));
        $this->assertFalse($approves('ci'), 'off by default');
        $this->assertFalse($approves('cli', ['X-Ulams-Client' => 'mcp']), 'an MCP client never approves, even with a CLI token');
        $this->assertFalse($approves(null, ['X-Ulams-Client' => 'MCP']));
        config(['course_as_code.ci_may_approve' => true]);
        $this->assertTrue($approves('ci'), 'the tenant lets a merged pull request count');
        $this->assertFalse($approves('agent'), 'agents can never approve');
        $this->assertFalse($approves('ci', ['X-Ulams-Client' => 'mcp']));
    }
}
