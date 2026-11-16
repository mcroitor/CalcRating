<?php

namespace App\Models\Concerns;

use App\Exceptions\ConcurrentModificationException;
use Illuminate\Support\Facades\DB;

trait HasOptimisticLocking
{
    /**
     * Update model using compare-and-swap on version.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ConcurrentModificationException
     */
    public function updateWithVersion(array $attributes, ?int $expectedVersion = null): bool
    {
        $currentVersion = $expectedVersion ?? (int) $this->version;

        $attributes['version'] = $currentVersion + 1;
        if ($this->usesTimestamps()) {
            $attributes[$this->getUpdatedAtColumn()] = $this->freshTimestamp();
        }

        $affected = DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->where('version', $currentVersion)
            ->update($attributes);

        if ($affected === 0) {
            throw new ConcurrentModificationException;
        }

        $this->fill($attributes);
        $this->syncOriginal();

        return true;
    }
}
