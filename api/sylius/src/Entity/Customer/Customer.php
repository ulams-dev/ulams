<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer
{
    /** The LMS user this customer is linked to: `<tenant slug>:<user id>` (ADR 0132). Set by the LMS, never by a buyer. */
    #[ORM\Column(name: 'ulams_user_ref', type: 'string', length: 191, unique: true, nullable: true)]
    protected ?string $ulamsUserRef = null;

    public function getUlamsUserRef(): ?string
    {
        return $this->ulamsUserRef;
    }

    public function setUlamsUserRef(?string $ulamsUserRef): void
    {
        $this->ulamsUserRef = $ulamsUserRef;
    }
}
