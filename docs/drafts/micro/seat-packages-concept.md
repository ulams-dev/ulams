# Seat Packages Concept

## Definition

A **seat package** is a digital product in Sylius, representing a licensed seat for a learner in the Learning Management System (LMS). The LMS maintains a seat pool tied to each order, and a manager is responsible for assigning these seats to learners. If a refund is issued, any unassigned seats are released back to the pool.

## Lifecycle

1. **Purchase**: A customer buys a seat package through the Sylius store.
2. **Provisioning**: The LMS provisions the seat package, allocating seats from the pool.
3. **Assignment**: A manager assigns the provisioned seats to specific learners.
4. **Expiry**: The seat package expires after the defined period, unless renewed.
5. **Refund**: If a refund is processed, any unassigned seats are released back to the pool.

## Open Questions

1. **TODO(verify)**: How are seat packages integrated with the billing system?
2. **TODO(verify)**: What are the policies for seat assignment and reallocation?
3. **TODO(verify)**: How is seat usage tracked and reported?
4. **TODO(verify)**: What are the procedures for handling seat over-provisioning?
5. **TODO(verify)**: How are seat packages managed in the event of a system failure or data loss?
