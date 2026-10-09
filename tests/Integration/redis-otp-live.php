<?php

// Opt-in: IdentityServer php tests/Integration/redis-otp-live.php
// Uses .env Redis credentials, an exclusive prefix, and no database operations.

use App\Exceptions\Auth\InvalidOtpException;
use App\Repositories\RedisOtpRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;

require dirname(__DIR__, 2).'/vendor/autoload.php';
/** @var list<string> $argv */
$argv = $_SERVER['argv'] ?? [];
if (file_exists(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Remove configuration cache before this opt-in check.');
}
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync'] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$prefix = $argv[2] ?? 'identity-otp-live-'.bin2hex(random_bytes(16)).':';
if (! preg_match('/^identity-otp-live-[a-f0-9]{32}:$/D', $prefix)) {
    throw new RuntimeException('Invalid isolated prefix.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app['config']->set('database.redis.options.prefix', $prefix);
$app['config']->set('database.redis.options.persistent', false);
$connection = Redis::connection();
$repo = new RedisOtpRepository;
if (! $connection instanceof PhpRedisConnection || $connection->client()->getOption(\Redis::OPT_PREFIX) !== $prefix) {
    throw new RuntimeException('Redis prefix isolation failed.');
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function rejected(Closure $operation): void
{
    try {
        $operation();
    } catch (InvalidOtpException) {
        return;
    }
    throw new RuntimeException('Expected INVALID_OTP.');
}

/** @return array{resource, array<int, resource>} */
function launch(string $mode, string $prefix, int $index = 0): array
{
    $process = proc_open([PHP_BINARY, __FILE__, $mode, $prefix, (string) $index], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Cannot start Redis worker.');
    fclose($pipes[0]);

    return [$process, $pipes];
}

/** @param array{resource, array<int, resource>} $worker */
function finish(array $worker): string
{
    [$process, $pipes] = $worker;
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0, 'Redis worker failed: '.$errors);

    return trim($output);
}

$mode = $argv[1] ?? 'main';
if ($mode === 'claim') {
    rejected(fn () => $repo->consume('basic', '1234', fn () => 'unexpected'));
    echo 'rejected';
    exit;
}
if ($mode === 'issue') {
    Redis::incr('ready');
    $deadline = microtime(true) + 15;
    while (! Redis::exists('start')) {
        check(microtime(true) < $deadline, 'Race barrier timeout.');
        usleep(10000);
    }
    $index = (int) $argv[3];
    echo $repo->issueWithCooldownAndReplacePending('race-'.$index, 'code-'.$index, 'shared', 'pending', 'attempt-'.$index);
    exit;
}
check($mode === 'main', 'Unknown mode.');
$subjects = ['basic', 'generation', 'foreign', 'expiry', 'ttl', 'pending', 'shared', 'blocked'];
for ($i = 0; $i < 8; $i++) {
    $subjects[] = 'race-'.$i;
}
$keys = ['ready', 'start'];
foreach ($subjects as $subject) {
    foreach (['code', 'version', 'claim', 'cooldown'] as $kind) {
        $keys[] = "otp:{$kind}:{$subject}";
    }
}
$workers = [];
try {
    echo "Isolated prefix: {$prefix}\n";
    $repo->put('basic', '1234');
    check($repo->get('basic') === '1234', 'put/get failed.');
    check(! $repo->canBeRequested('basic') && $repo->secondsUntilNextRequest('basic') > 0, 'Cooldown missing.');
    rejected(fn () => $repo->consume('basic', 'wrong', fn () => null));
    try {
        $repo->consume('basic', '1234', fn () => throw new RuntimeException('callback-failure'));
    } catch (RuntimeException $exception) {
        check($exception->getMessage() === 'callback-failure', 'Unexpected callback error.');
    }
    check(! Redis::exists('otp:claim:basic') && $repo->get('basic') === '1234', 'Failed callback lost code or leaked claim.');
    $repo->consume('basic', '1234', function () use ($prefix): void {
        check(finish(launch('claim', $prefix)) === 'rejected', 'Concurrent claim accepted.');
    });
    check(! Redis::exists('otp:code:basic') && ! Redis::exists('otp:version:basic'), 'Successful consume left challenge keys.');
    rejected(fn () => $repo->consume('basic', '1234', fn () => null));
    echo "PASS claim, wrong code, exception retry, concurrent process, single use\n";

    $repo->put('generation', '1111');
    $repo->consume('generation', '1111', fn () => $repo->put('generation', '2222'));
    check($repo->get('generation') === '2222' && ! Redis::exists('otp:claim:generation'), 'New generation damaged.');
    $repo->consume('generation', '2222', fn () => null);
    foreach ([false, true] as $throw) {
        $repo->put('foreign', '3333');
        try {
            $repo->consume('foreign', '3333', function () use ($repo, $throw): void {
                $repo->put('foreign', '4444');
                Redis::setex('otp:claim:foreign', 90, 'other-owner:'.Redis::get('otp:version:foreign'));
                if ($throw) {
                    throw new RuntimeException('foreign-failure');
                }
            });
        } catch (RuntimeException $exception) {
            check($exception->getMessage() === 'foreign-failure', 'Unexpected foreign-claim error.');
        }
        check(str_starts_with(Redis::get('otp:claim:foreign'), 'other-owner:') && $repo->get('foreign') === '4444', 'Foreign claim or new code deleted.');
        Redis::del('otp:claim:foreign');
    }
    echo "PASS generation replacement and foreign-owner protection on success/failure\n";

    $repo->put('expiry', '5555');
    $repo->consume('expiry', '5555', function (): void {
        Redis::pexpire('otp:code:expiry', 1);
        Redis::pexpire('otp:version:expiry', 1);
        $deadline = microtime(true) + 2;
        while (Redis::exists('otp:code:expiry') || Redis::exists('otp:version:expiry')) {
            check(microtime(true) < $deadline, 'Redis expiry timeout.');
            usleep(1000);
        }
    });
    check($repo->get('expiry') === null && ! Redis::exists('otp:claim:expiry'), 'Expired code resurrected or claim leaked.');
    $repo->put('ttl', '6666');
    Redis::expire('otp:code:ttl', 2);
    Redis::expire('otp:version:ttl', 2);
    check(! $repo->extendTtlIfCurrent('ttl', 'wrong'), 'Wrong value extended TTL.');
    check($repo->extendTtlIfCurrent('ttl', '6666') && Redis::ttl('otp:code:ttl') > 80 && Redis::ttl('otp:version:ttl') > 80, 'TTL extension failed.');
    $repo->consume('ttl', '6666', function () use ($repo): void {
        check(! $repo->replaceIfUnclaimed('ttl', '7777'), 'Claimed value replaced.');
    });
    check($repo->replaceIfUnclaimed('ttl', '7777'), 'Unclaimed replacement failed.');
    $repo->forget('ttl');
    echo "PASS real Redis expiration, conditional TTL, guarded replacement, forget\n";

    Redis::setex('ready', 20, 0);
    for ($i = 0; $i < 8; $i++) {
        $workers[] = launch('issue', $prefix, $i);
    }
    $deadline = microtime(true) + 15;
    while ((int) Redis::get('ready') !== 8) {
        check(microtime(true) < $deadline, 'Workers not ready.');
        usleep(10000);
    }
    Redis::setex('start', 20, 1);
    $winners = [];
    foreach ($workers as $index => $worker) {
        unset($workers[$index]);
        $result = (int) finish($worker);
        if ($result === 0) {
            $winners[] = $index;
        } else {
            check($result > 0 && $result <= 60, 'Invalid cooldown result.');
        }
    }
    check(count($winners) === 1, 'Reservation race had multiple winners.');
    $winner = $winners[0];
    check($repo->get('pending') === 'attempt-'.$winner, 'Pending does not belong to winner.');
    for ($i = 0; $i < 8; $i++) {
        check($repo->get('race-'.$i) === ($i === $winner ? 'code-'.$i : null), 'Rejected reservation wrote a challenge.');
    }
    Redis::del('otp:cooldown:shared');
    $repo->consume('pending', 'attempt-'.$winner, function () use ($repo): void {
        check($repo->issueWithCooldownAndReplacePending('blocked', '8888', 'shared', 'pending', 'blocked-attempt') > 0, 'Claimed pending replaced.');
        check($repo->get('blocked') === null, 'Blocked reservation wrote code.');
    });
    check($repo->issueWithCooldownAndReplacePending('blocked', '8888', 'shared', 'pending', 'next-attempt') === 0, 'Reservation after cooldown failed.');
    echo "PASS atomic phone reservation: 8 independent processes, 1 winner; pending claim protection\n";
} finally {
    foreach ($workers as [$process, $pipes]) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);
    }
    Redis::del(...$keys);
    check((int) Redis::exists(...$keys) === 0, 'Cleanup left test keys.');
    echo "CLEANUP PASS: 0 remaining test keys\n";
}
