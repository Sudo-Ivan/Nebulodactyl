<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Marketplace;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Models\Permission;

class GameVersionsRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_MOD_VERSION;
    }

    public function rules(): array
    {
        return [];
    }
}
