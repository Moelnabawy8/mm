<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;

abstract class Controller extends BaseController
{
    use ApiResponser;

    /**
     * The tenant id all PR records are scoped to (company owner id).
     */
    protected function ownerId(): int
    {
        return (int) Auth::user()->creatorId();
    }
}
