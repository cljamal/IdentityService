<?php

namespace App\Actions\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Auth\History\IdentityChangeAction;
use App\Events\Ops\AccountDeleted;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class DeleteAccountAction
{
    use AsAction;

    public function __construct(
        private AuthProviderRepositoryInterface $providers,
        private IdentityChangeLogRepositoryInterface $history,
        private AuthSessionRepositoryInterface $sessions,
    ) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Освобождает phone/email/username всех привязанных identity,
            // чтобы ими мог воспользоваться кто-то другой — учётка при этом
            // никогда физически не удаляется (SoftDeletes), восстановление
            // не планируется, поэтому обратимость этого шага не нужна.
            $this->providers->releaseAllForUser($user);

            $this->sessions->revokeAllForUser($user);

            $user->delete();

            $this->history->log($user, null, IdentityChangeAction::AccountDeleted, null, null);
        });

        // Инвалидируем токен, которым выполнялся этот запрос.
        IdApiGuard::current()->logout();

        AccountDeleted::dispatch($user);
    }
}
