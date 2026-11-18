<?php

namespace App\Support;

/**
 * Guarda o owner_user_id ativo do request/job atual (o professor dono dos
 * dados sendo acessados — uma linha da tabela `users` do ProvaGen). Estado
 * estático simples: cada request PHP-FPM é um processo isolado, então isso é
 * seguro para HTTP. Para filas (queue:work, processo de longa duração), cada
 * Job precisa chamar OwnerContext::set() explicitamente no início do
 * handle() a partir de um owner_user_id que ele mesmo capturou no
 * construtor — ver AppServiceProvider, que limpa o contexto entre jobs para
 * não vazar entre execuções.
 */
class OwnerContext
{
    protected static ?int $ownerUserId = null;

    public static function set(?int $ownerUserId): void
    {
        static::$ownerUserId = $ownerUserId;
    }

    public static function get(): ?int
    {
        return static::$ownerUserId;
    }

    public static function clear(): void
    {
        static::$ownerUserId = null;
    }
}
