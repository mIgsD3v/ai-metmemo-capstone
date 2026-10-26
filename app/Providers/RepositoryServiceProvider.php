<?php

namespace App\Providers;

use App\Repositories\Contracts\AssistantConversationRepositoryInterface;
use App\Repositories\Contracts\CardRepositoryInterface;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Repositories\Eloquent\AssistantConversationRepository;
use App\Repositories\Eloquent\CardRepository;
use App\Repositories\Eloquent\ContactRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementation. Services depend on the interfaces only.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AssistantConversationRepositoryInterface::class => AssistantConversationRepository::class,
        CardRepositoryInterface::class => CardRepository::class,
        ContactRepositoryInterface::class => ContactRepository::class,
    ];
}
