<?php

namespace App\Actions\Catalog;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die Tags eines Datensatzes.
 *
 * Nimmt sowohl bestehende Tag-IDs als auch neue Bezeichnungen entgegen, damit
 * Tags direkt im Formular angelegt werden koennen.
 */
class SyncTags
{
    /**
     * @param  array<int, int|string>  $tags  IDs bestehender Tags oder neue Bezeichnungen
     */
    public function __invoke(Model $model, array $tags): void
    {
        DB::transaction(function () use ($model, $tags): void {
            $ids = collect($tags)
                ->map(fn (int|string $tag): ?int => is_numeric($tag)
                    ? (int) $tag
                    : $this->resolveByName((string) $tag))
                ->filter()
                ->unique()
                ->all();

            $model->tags()->sync($ids);
        });

        $model->unsetRelation('tags');
    }

    private function resolveByName(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Tag::query()->firstOrCreate(['name' => $name])->getKey();
    }
}
