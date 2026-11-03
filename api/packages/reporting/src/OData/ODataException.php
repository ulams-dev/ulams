<?php

namespace Ulams\Reporting\OData;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** An error the way an OData client expects it: `{"error": {"code", "message"}}` with the matching HTTP status. */
class ODataException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public static function notImplemented(string $option): self
    {
        return new self(501, "The query option {$option} is not supported. This feed returns the report as it was saved: its filters, grouping and order are part of the report. Supported: \$select, \$top, \$skip, \$count and \$format=json.");
    }

    public function response(): JsonResponse
    {
        return response()->json(['error' => ['code' => (string) $this->status, 'message' => $this->getMessage()]], $this->status, ['OData-Version' => '4.0']);
    }
}
