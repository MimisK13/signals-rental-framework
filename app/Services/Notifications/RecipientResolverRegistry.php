<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Models\Member;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Plugin-extensible registry of notification recipient rule resolvers (P24).
 */
class RecipientResolverRegistry
{
    /** @var array<string, RecipientResolver> */
    private array $resolvers = [];

    public function register(RecipientResolver $resolver): void
    {
        $this->resolvers[$resolver->type()] = $resolver;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $type): RecipientResolver
    {
        return $this->resolvers[$type]
            ?? throw new InvalidArgumentException("Unknown recipient resolver type [{$type}].");
    }

    public function has(string $type): bool
    {
        return isset($this->resolvers[$type]);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        $types = array_keys($this->resolvers);
        sort($types);

        return $types;
    }

    /**
     * Resolve all recipients from the given rules, deduplicated by member ID.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return Collection<int, Member>
     */
    public function resolveAll(array $rules, NotificationContextData $context): Collection
    {
        $recipients = collect();

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? null;

            if (! is_string($type) || ! $this->has($type)) {
                continue;
            }

            $recipients = $recipients->merge($this->get($type)->resolve($rule, $context));
        }

        return $recipients
            ->filter(fn (mixed $member): bool => $member instanceof Member)
            ->unique(fn (Member $member): int => $member->id)
            ->values();
    }
}
