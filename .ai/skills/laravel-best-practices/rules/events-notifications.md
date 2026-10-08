# Events and Delivery in IdentityService

## Classify Events Before Changing Timing

Read architecture.md. Internal invariant events, OTP delivery and operational
broadcasts have different responsibilities. Do not apply ShouldDispatchAfterCommit,
queues or framework Notifications to every event.

UserHasNoRole invokes AssignDefaultRole synchronously during user creation. The role
must exist before IdApiGuard issues a JWT. Deferring this listener until commit or
queue execution can produce a token with the wrong role.

For outward events depending on committed data, dispatch after the relevant
transaction or use ShouldDispatchAfterCommit when its semantics fit.
DeleteAccountAction dispatches AccountDeleted after its transaction. Preserve ordering
and failure behavior; do not move dispatch calls mechanically.

## Preserve OTP Fan-Out

OtpCodeIssued follows challenge storage. RouteOtpCodeDelivery separates
OtpCodeBroadcast from OtpCodeDeliveryRequested; DeliverOtpCode uses SmsNotifier
and EmailNotifier. Broadcast payloads must remain masked and free of raw OTP codes.
Preserve challenge expiry, resend cooldown and client scope.

Do not replace these collaborators with Laravel Notifications or add queued delivery
merely to follow a generic default. A queue change needs a concrete latency requirement
and a design for expiry, retries and duplicate delivery.

## Preserve Operational Broadcast Semantics

OpsBroadcastEvent uses ShouldBroadcastNow and ShouldRescue with explicit names,
payloads and owning-client channels. Transport failure must not turn a completed
authentication operation into an error. Preserve these semantics for its subclasses.

OtpCodeBroadcast has a separate contract and does not implement ShouldRescue.
Do not extend rescue behavior to unrelated events without establishing their required
failure handling. Never broadcast credentials, refresh tokens or raw OTP codes.

## Follow Listener Discovery and Deployment Boundaries

Follow existing listener discovery and registration. Register manually when discovery
does not cover the listener or explicit registration is justified.
Rebuilding event caches belongs to deployment; examples do not authorize optimize
or changes to the running environment.

## Use Framework Notifications Where the Feature Uses Them

For features using Laravel Notifications, queue external delivery when it may finish
after the response. Use afterCommit() for queued notifications depending on committed
data. Choose channel queues, on-demand routing and locales to fit the feature.
These options do not require rewriting current notifier interfaces.
