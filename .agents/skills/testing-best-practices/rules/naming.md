# Test Organization and Naming

Follow IdentityService's existing PHPUnit conventions. Feature/Auth groups related
HTTP and authentication contracts; Unit/Auth contains focused collaborator tests.
Files need not mirror application directories or map one-to-one to source classes.

Place a new case beside the behavior it exercises. Use a new file when it provides
a coherent behavior group, not merely because a new Action exists. Follow nearby
test_ method naming and data-provider style.

Name tests for observable behavior and relevant conditions: a reused refresh token
revokes its session; another client's token is rejected; a role is included on login.
Include an HTTP status in the name when it clarifies the contract, not as a mandatory
suffix. Avoid names tied to incidental private method calls.

Use existing groups when useful. Do not restructure the suite or introduce a new
naming convention as part of an unrelated change.
