<?php

namespace App\Livewire\Concerns;

use App\Models\TableConfiguration;

/**
 * Macht eine Listentabelle konfigurierbar: Spalten ein- und ausblenden,
 * Reihenfolge und Breite festlegen.
 *
 * Die Konfiguration wird global gespeichert und gilt fuer alle Benutzer — es
 * gibt bewusst keine benutzerspezifischen Ansichten und keine gespeicherten
 * Filteransichten.
 *
 * Die nutzende Komponente muss `tableKey()` und `columnDefinitions()`
 * bereitstellen.
 */
trait WithConfigurableTable
{
    /** @var array<int, array{key: string, visible: bool, width: int|null}> */
    public array $tableColumns = [];

    public bool $showTableSettings = false;

    /**
     * Eindeutiger Schluessel dieser Tabelle, zum Beispiel "customers".
     */
    abstract protected function tableKey(): string;

    /**
     * Alle verfuegbaren Spalten in ihrer Standardreihenfolge.
     *
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool}>
     */
    abstract protected function columnDefinitions(): array;

    public function mountWithConfigurableTable(): void
    {
        $this->tableColumns = $this->mergeStoredColumns(
            TableConfiguration::query()
                ->where('table_key', $this->tableKey())
                ->value('columns') ?? [],
        );
    }

    public function toggleColumn(string $key): void
    {
        if ($this->columnDefinitions()[$key]['fixed'] ?? false) {
            return;
        }

        $this->tableColumns = array_map(
            fn (array $column): array => $column['key'] === $key
                ? [...$column, 'visible' => ! $column['visible']]
                : $column,
            $this->tableColumns,
        );

        $this->persistTableConfiguration();
    }

    public function moveColumn(string $key, int $offset): void
    {
        $keys = array_column($this->tableColumns, 'key');
        $index = array_search($key, $keys, strict: true);

        if ($index === false) {
            return;
        }

        $target = $index + $offset;

        if ($target < 0 || $target >= count($this->tableColumns)) {
            return;
        }

        [$this->tableColumns[$index], $this->tableColumns[$target]] =
            [$this->tableColumns[$target], $this->tableColumns[$index]];

        $this->persistTableConfiguration();
    }

    public function setColumnWidth(string $key, ?int $width): void
    {
        $this->tableColumns = array_map(
            fn (array $column): array => $column['key'] === $key
                ? [...$column, 'width' => $width]
                : $column,
            $this->tableColumns,
        );

        $this->persistTableConfiguration();
    }

    public function resetTableConfiguration(): void
    {
        TableConfiguration::query()->where('table_key', $this->tableKey())->delete();

        $this->tableColumns = $this->mergeStoredColumns([]);
    }

    /**
     * Kopfzeilen für die TallStackUI-Tabelle in konfigurierter Reihenfolge.
     *
     * @return array<int, array{index: string, label: string, sortable: bool, width?: string}>
     */
    public function tableHeaders(): array
    {
        $definitions = $this->columnDefinitions();

        return collect($this->tableColumns)
            ->filter(fn (array $column): bool => $column['visible'] && isset($definitions[$column['key']]))
            ->map(function (array $column) use ($definitions): array {
                $definition = $definitions[$column['key']];

                $header = [
                    'index' => $column['key'],
                    'label' => $definition['label'],
                    'sortable' => $definition['sortable'] ?? true,
                ];

                if ($column['width']) {
                    $header['width'] = $column['width'].'px';
                }

                return $header;
            })
            ->values()
            ->all();
    }

    /**
     * Spaltenkonfiguration fuer die Einstellungsansicht: Reihenfolge, Titel,
     * Sichtbarkeit, Breite und ob die Spalte fest eingeblendet bleibt.
     *
     * @return array<int, array{key: string, label: string, visible: bool, width: int|null, fixed: bool}>
     */
    public function columnSettings(): array
    {
        $definitions = $this->columnDefinitions();

        return collect($this->tableColumns)
            ->filter(fn (array $column): bool => isset($definitions[$column['key']]))
            ->map(fn (array $column): array => [
                'key' => $column['key'],
                'label' => $definitions[$column['key']]['label'],
                'visible' => $column['visible'],
                'width' => $column['width'],
                'fixed' => $definitions[$column['key']]['fixed'] ?? false,
            ])
            ->values()
            ->all();
    }

    public function isColumnVisible(string $key): bool
    {
        foreach ($this->tableColumns as $column) {
            if ($column['key'] === $key) {
                return $column['visible'];
            }
        }

        return false;
    }

    /**
     * Verbindet die gespeicherte Konfiguration mit den aktuellen Definitionen.
     *
     * Neue Spalten werden ergaenzt, entfallene entfernt — die Konfiguration
     * überlebt dadurch Änderungen am Code.
     *
     * @param  array<int, array{key: string, visible: bool, width: int|null}>  $stored
     * @return array<int, array{key: string, visible: bool, width: int|null}>
     */
    private function mergeStoredColumns(array $stored): array
    {
        $definitions = $this->columnDefinitions();
        $storedByKey = collect($stored)->keyBy('key');

        $configured = collect($stored)
            ->filter(fn (array $column): bool => isset($definitions[$column['key']]))
            ->map(fn (array $column): array => [
                'key' => $column['key'],
                'visible' => (bool) ($column['visible'] ?? true),
                'width' => $column['width'] ?? null,
            ]);

        $missing = collect($definitions)
            ->reject(fn (array $definition, string $key): bool => $storedByKey->has($key))
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                // Spalten duerfen sich abgeschaltet einreihen; ohne Angabe
                // bleibt es beim bisherigen Verhalten.
                'visible' => $definition['default_visible'] ?? true,
                'width' => $definition['width'] ?? null,
            ])
            ->values();

        return $configured->concat($missing)->values()->all();
    }

    private function persistTableConfiguration(): void
    {
        TableConfiguration::query()->updateOrCreate(
            ['table_key' => $this->tableKey()],
            ['columns' => $this->tableColumns],
        );
    }
}
