<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $fabric_pdf_id
 * @property string $policy_version_id
 * @property string $variant_id
 * @property int $user_id
 */
class PolicyCertificate extends Model
{
    protected $table = 'policy_certificates';

    protected $guarded = ['id'];

    protected $casts = ['fabric_pdf_id' => 'integer', 'user_id' => 'integer'];
}
