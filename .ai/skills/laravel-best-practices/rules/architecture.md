# IdentityService Architecture: Actions + Events

These are project-specific requirements, grounded in the existing IdentityService implementation.
Use them instead of generic service-layer examples. Read CLAUDE.md first; owner requirements have
priority. This architecture is Actions + Events with strategies, repositories and typed collaborators.
Do not introduce an AuthService, UserService, OtpService or equivalent facade over the existing flows.

## Responsibilities and dependency direction

| Component | Responsibility | Existing examples |
| --- | --- | --- |
| Controller | Adapt HTTP input, obtain the authenticated user, invoke an operation, return an API resource | AuthController, SessionController |
| Action | Execute one application use case, coordinate validation, capabilities, collaborators and result | RegisterAction, ResetPasswordAction, DeleteAccountAction |
| Strategy | Implement provider-specific rules and behavior through explicit capabilities | PhoneOtpStrategy, EmailPasswordStrategy, UsernamePasswordStrategy |
| Support collaborator | Reuse a focused mechanism without owning the HTTP use case | OtpChallenge, PhoneChangeCoordinator, RegistrationVerifier |
| Repository contract / implementation | Encapsulate persistence, client-scoped lookups and atomic storage operations | AuthProviderRepositoryInterface, EloquentAuthSessionRepository |
| Event | Carry a typed fact or delivery request to interested consumers | UserHasNoRole, OtpCodeIssued, UserRegistered |
| Listener | React to one event, delegate a reaction or route notification fan-out | AssignDefaultRole, RouteOtpCodeDelivery, DeliverOtpCode |
| Guard | Own JWT issuance, session tracking, refresh rotation and token acceptance | IdApiGuard |

The usual flow is Controller → Action → Strategy / support collaborator / repository / guard.
An operation emits an Event at the point where the fact becomes true; listeners handle its reactions.
Producers include actions, strategies, repositories, the guard and model lifecycle hooks. Do not move
all dispatches into controllers or actions just to make the diagram uniform.

Existing small entry points, such as AuthController::logout(), do not justify a broad refactor.
For new application use cases, use an Action rather than expanding a controller or introducing a
parallel service layer. Do not convert every collaborator or listener into an Action.

## Actions are the application entry points

- Put auth use cases in app/Actions/Auth/ with an operation-specific name ending in Action.
- Use `Lorisleiva\Actions\Concerns\AsAction` and implement handle(); invoke through ::run().
  This is a container-backed invocation convention, not a hand-written static business method.
- Follow nearby final readonly actions when compatible with the required state and traits.
- Inject collaborators through the constructor. Pass operation input as handle() arguments.
- Keep actions independent of Request, HTTP responses and API resource serialization. Existing
  actions accept AuthProviderName, User and input arrays and return TokenPair, a collection or void.
  Preserve those contracts; do not mandate DTOs or a command bus for every operation.
- A controller wraps the result in TokenResource, SessionResource or MessageResource. AuthException
  subclasses own stable error-code rendering; do not duplicate that mapping in every action.
- Compose an existing Action when the same use case is needed elsewhere. For example,
  ConfirmAccountDeletionAction delegates successful confirmation to DeleteAccountAction.

Existing controller/action boundary:

```php
public function login(AuthProviderName $provider, Request $request): TokenResource
{
    return TokenResource::make(LoginAction::run($provider, $request->all()));
}
```

Study app/Actions/Auth/LoginAction.php and RegisterAction.php before adding an authentication flow.
Do not replace ::run() with controller injection of a newly invented service merely because a
generic Laravel example uses one.

## Strategies own provider capabilities and validation rules

Actions resolve a strategy through AuthStrategyResolver; config/identity.php maps enabled providers
to implementations. Do not duplicate the provider switch or read that map at every call site.

For operations beyond the base AuthStrategy contract, check the matching capability
(RegistersIdentity, ChangesPassword, ResetsPassword, ChangesIdentifier, ConfirmsDeletion or
IssuesVerificationCode). Unsupported capabilities raise UnsupportedAuthOperationException.

Preserve the established order:

1. Resolve the provider and check the required capability.
2. Normalize through NormalizesInput when supported.
3. Validate using the strategy's rules for that operation.
4. Execute the provider behavior and coordinate the application result.

LoginAction uses rules()/authenticate(); RegisterAction uses
registrationRules()/register(). Validation belongs to this orchestration and the provider's rules.
Do not move all validation into Form Requests or put provider-specific rules into controllers as
an unrelated architectural cleanup.

Keep shared OTP mechanics in existing support classes. Deciding a rescue destination belongs to
the strategy/resolver; the reusable code challenge must not acquire knowledge of all providers.

## Events and listeners are explicit collaboration boundaries

Follow the three existing event categories:

- app/Events/Auth/: internal reactions, such as UserHasNoRole and ClientDeactivated.
- app/Events/Notifications/: OTP issuance, routing and delivery requests.
- app/Events/Ops/: public signals to a downstream client's gateway via its private channel.

Events carry typed data. Keep business orchestration out of event constructors and broadcast payload
methods. A listener's typed handle(Event $event) is discovered by Laravel; verify registration with
IdentityServer php artisan event:list when introducing or changing a listener.

A listener may use an existing repository or notifier directly for its focused reaction, or invoke
an Action when it needs an existing use case. Do not force a service class or another Action between
every event and its listener. Do not use listener return values to supply the Action's result.

### OTP delivery: one producer event, centrally routed reactions

```text
Strategy / support collaborator
  → OtpCodeIssued
  → RouteOtpCodeDelivery
      → OtpCodeBroadcast (masked notice without the code)
      → OtpCodeDeliveryRequested
          → DeliverOtpCode → SmsNotifier / EmailNotifier
```

OTP-producing code stores the challenge and emits OtpCodeIssued once. It must not also call a notifier,
broadcast directly or dispatch the two downstream events itself. RouteOtpCodeDelivery owns fan-out;
DeliverOtpCode owns delivery through the notifier contracts bound in AppServiceProvider.

The raw OTP is present only in the internal issuance/delivery path. OtpCodeBroadcast deliberately
omits it and masks the contact; never add the code to this monitoring channel.

### Internal reactions: preserve immediate effects where required

EloquentAuthProviderRepository::createUserWithIdentity() dispatches UserHasNoRole within its
transaction. AssignDefaultRole runs synchronously; the role is then available when User generates
JWT claims. Do not queue or defer this listener, or add after-commit dispatch to this event, without
redesigning and verifying the token-issuance contract.

Client::booted() emits ClientActivated / ClientDeactivated only when is_active changes. Listeners
broadcast the service state and revoke the deactivated client's sessions. IdApiGuard's live
deactivation check remains the security boundary; the revocation listener is not its replacement.

### Ops broadcasts: retain channel, payload and failure semantics

OpsBroadcastEvent implements ShouldBroadcastNow and ShouldRescue. These signals target
client.{client_id} using the owning Client, with explicit broadcastAs()/broadcastWith() contracts.
Do not broadcast a whole model or derive the target client from untrusted request input.

The rescue behavior prevents a broadcast transport failure from turning an already completed auth
operation into a 500. Preserve it for Ops events. OtpCodeBroadcast does not implement ShouldRescue;
do not silently apply the same failure policy to every event or delivery listener.

Keep event meaning and dispatch count intact. UserRegistered fires for an immediately verified
registration, successful registration verification, or first phone-OTP authentication that creates a
user. Do not add a second dispatch in LoginAction for that phone flow. Session revocation and account
deletion have separate events and reasons; do not emit misleading extra signals for convenience.

## Keep persistence and client boundaries explicit

Use existing repository contracts for the storage operations they own. Bind implementations in
AppServiceProvider; OTP tests can replace OtpRepositoryInterface with FakeOtpRepository.
Depend on notifier contracts at the delivery boundary. Add new interfaces when there is a real
boundary or interchangeable implementation, not an interface for every Action.

Do not move identity/session queries into controllers, event payloads or generic services. Preserve
the repository's CurrentClient scoping, ownership checks and database constraints. IdApiGuard owns
token checks and rotation; an Action must not reimplement the guard in a service.

CurrentClient is authenticated request state populated by AuthenticateClient. Do not replace it
with Context::add('tenant_id', request()->header(...)) or trust a caller-supplied client identifier.
The client middleware must precede auth:id-api where both apply.

Constructor injection is preferred for collaborators. Existing IdApiGuard::current(), Action::run()
and AuthStrategyResolver's explicit container-based strategy resolution are intentional entry points;
do not replace them mechanically under a generic prohibition on static calls or container resolution.

## Transactions, timing and concurrent operations

Keep transaction boundaries with the operation or repository that owns the atomic change.
DeleteAccountAction commits identity release, session revocation, soft deletion and history together,
then invalidates the current token and emits AccountDeleted. Preserve that sequence.

For a new event inside a transaction, decide whether its listener contributes to the atomic operation
or observes a committed outcome. Immediate internal reactions may need the transaction; outward
effects requiring committed data must wait for commit. Do not apply the generic after-commit rule
to all events. Read rules/events-notifications.md with these project-specific timing requirements.

Do not automatically queue listeners, use defer() or parallelize auth work. Those choices change
ordering, failure handling and access to CurrentClient. Queued work needs explicit authenticated
client/user context and must resolve scoped data safely in its own process. Ask the owner before
changing existing synchronous behavior.

Use the existing conditional update for refresh-token rotation. Losing a concurrent rotation returns
an invalid-token error without revoking the winning session; replay of a previously rotated token
has different semantics. Do not substitute a cache lock for that distinction. Introduce a lock only
for a demonstrated race, with an appropriate shared store, transaction and bounded lifetime.

Preserve stable ordering for paginated collections and multibyte-safe text handling where relevant.
These implementation practices do not justify replacing the application's architecture.

## Before finishing a future feature

Trace the nearest existing flow from controller to action, capability, persistence and emitted events.
Reuse its contracts and identify which component owns each new responsibility. Read related
routing, validation, security and event rules with this architecture taking precedence for local
structure and timing. If source patterns conflict or a new boundary is uncertain, ask the owner.

For behavior changes, verify the use-case outcome and the relevant reactions, including client
isolation, event count/payload, token claims and failure behavior. In tests, fake only the events
under inspection when real listeners provide required setup; Event::fake() for everything can hide
role assignment and delivery behavior. Follow testing-best-practices for selecting actual coverage.

Useful source anchors: app/Actions/Auth/, app/Auth/Strategies/Contracts/,
app/Repositories/EloquentAuthProviderRepository.php, app/Auth/Guards/IdApiGuard.php,
app/Listeners/Notifications/, app/Events/Ops/OpsBroadcastEvent.php and tests/Feature/Events/Ops/.
