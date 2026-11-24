<?php

namespace NiekPH\LaravelVisitorTracking\Models\Concerns;

use NiekPH\LaravelVisitorTracking\VisitorTracking;

/**
 * Gives the model string keys that are generated on create when `database.id_type` is uuid or ulid,
 * like Laravel's HasUuids and HasUlids. Auto-incrementing keys otherwise.
 */
trait UsesConfiguredIdType
{
    public function initializeUsesConfiguredIdType(): void
    {
        if (VisitorTracking::newId() === null) {
            return;
        }

        $this->usesUniqueIds = true;
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    public function newUniqueId(): ?string
    {
        return VisitorTracking::newId();
    }
}
