# Validation in IdentityService

## Keep the Existing Input Boundary

Read architecture.md first. Authentication controllers are HTTP adapters and may pass
$request->all() to an Action. Provider-specific validation belongs to the selected
strategy, not to a universal Form Request.

Follow the operation's existing sequence: resolve the strategy, check its capability,
normalize input when it implements NormalizesInput, validate using that capability's
rules, then execute. Follow nearby Actions and strategy contracts for method names.
Do not move validation before normalization or duplicate it in a controller.

Some Actions delegate validation to existing collaborators. Trace the complete flow
before concluding that an Action lacks validation. Use a Form Request only when the
boundary benefits from it; it is not a required replacement for strategy rules.

## Select Fields at the Write Boundary

Passing input to an Action does not authorize passing that array to Eloquent.
Strategies and repositories must select intended fields explicitly. Preserve model
mass-assignment protection; never allow input to choose client_id, user ownership,
roles, session state or token hashes.

Where a Form Request exists, use validated() or safe() and select write fields.
Validated control fields and nested keys are not automatically model attributes.
Test unexpected payload keys where accepting them could cross a security boundary.

## Preserve Client Scope and Error Contracts

Identity lookup and uniqueness rules must use the resolved client. Global uniqueness
for phone, email or username would reject legitimate accounts of another client.
Validation does not replace authorization, capabilities or repository ownership.

Preserve Laravel's validation status and field errors where currently used. Keep
AuthException's stable code/status contracts for domain errors. Missing or malformed
refresh tokens follow the existing invalid-token 401 contract; do not replace it
with a generic required-field 422 response.

## Keep Rules Readable and Enforce Mutable Invariants

Follow nearby rule syntax. Use rule objects and conditional rules when clearer.
Put cross-field checks in the existing validator or strategy boundary; do not create
a Form Request merely to use its after() hook. Avoid dependent queries when
prerequisite fields already failed validation.

Validation cannot prevent a race with persistence. Preserve database constraints,
client-scoped queries, atomic token rotation and appropriate transactions.
Prepare schema changes when needed; execution belongs to the owner.
