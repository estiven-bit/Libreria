<?php

namespace LibreriaGabi\OAuth2\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;

    public function setName(string $name): void { $this->name = $name; }
    public function setRedirectUri(string $uri): void { $this->redirectUri = $uri; }
    public function setConfidential(bool $confidential): void { $this->isConfidential = $confidential; }
}
