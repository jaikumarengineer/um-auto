<?php

namespace Modules\Quality\Support;

use App\Services\SettingService;
use Illuminate\Support\Facades\Schema;

class QualityAlertCatalog
{
    public const DEPARTMENTS = [
        'purchase' => 'Purchase',
        'incoming' => 'Incoming',
    ];

    public const STATUSES = [
        'overdue' => 'Overdue',
        'pending' => 'Pending',
        'completed' => 'Completed',
    ];

    public const REPORTS = [
        'po_vs_pi' => 'PO vs PI',
        'incoming_inspection' => 'Incoming inspection reports',
    ];

    public const SUMMARIES = [
        'daily' => 'Day wise',
        'weekly' => 'Weekly',
        'monthly' => 'Monthwise',
    ];

    /**
     * Older digest types kept so previously logged alerts can still be opened.
     *
     * @var array<string, array{label: string, department: string|null}>
     */
    public const LEGACY = [
        'daily_summary' => ['label' => 'Day summary', 'department' => null],
        'purchase_summary' => ['label' => 'Purchase summary', 'department' => 'purchase'],
        'incoming_summary' => ['label' => 'Incoming summary', 'department' => 'incoming'],
        'po_vs_pi' => ['label' => 'PO vs PI', 'department' => 'purchase'],
        'overdue_po' => ['label' => 'Overdue PO', 'department' => 'purchase'],
        'uninspected_lots' => ['label' => 'Waiting inspection', 'department' => 'incoming'],
        'quality_hold' => ['label' => 'Quality hold', 'department' => 'incoming'],
    ];

    /**
     * @return array<string, array{label: string, kind: string, report: string, department: string, status: string|null, cadence: string|null}>
     */
    public static function alertTypes(): array
    {
        $types = [];

        foreach (self::REPORTS as $report => $reportLabel) {
            $department = $report === 'po_vs_pi' ? 'purchase' : 'incoming';

            foreach (self::STATUSES as $status => $statusLabel) {
                $types["{$report}.{$status}"] = [
                    'label' => $reportLabel.' · '.$statusLabel,
                    'kind' => 'status',
                    'report' => $report,
                    'department' => $department,
                    'status' => $status,
                    'cadence' => null,
                ];
            }

            foreach (self::SUMMARIES as $cadence => $cadenceLabel) {
                $types["{$report}.summary.{$cadence}"] = [
                    'label' => $reportLabel.' · '.$cadenceLabel.' summary',
                    'kind' => 'summary',
                    'report' => $report,
                    'department' => $department,
                    'status' => null,
                    'cadence' => $cadence,
                ];
            }
        }

        return $types;
    }

    /**
     * @return array<string, string>
     */
    public static function labelsFor(?string $department = null, bool $includeLegacy = false): array
    {
        $labels = [];

        foreach (self::alertTypes() as $key => $meta) {
            if ($department !== null && $meta['department'] !== $department) {
                continue;
            }

            $labels[$key] = __($meta['label']);
        }

        if ($includeLegacy) {
            foreach (self::LEGACY as $key => $meta) {
                if ($department !== null && $meta['department'] !== $department) {
                    continue;
                }

                $labels[$key] = __($meta['label']);
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public static function departmentLabels(): array
    {
        return self::translateMap(self::DEPARTMENTS);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::translateMap(self::STATUSES);
    }

    /**
     * @return array<string, string>
     */
    public static function reportLabels(): array
    {
        return self::translateMap(self::REPORTS);
    }

    /**
     * @return array<string, string>
     */
    public static function summaryLabels(): array
    {
        return self::translateMap(self::SUMMARIES);
    }

    public static function matches(string $alertType, ?array $preferences): bool
    {
        $preferences = self::normalize($preferences);
        $catalog = self::alertTypes()[$alertType] ?? null;

        if (! $preferences['enabled'] || $catalog === null) {
            return false;
        }

        if (! self::selectionAllows($preferences['departments'], [$catalog['department']])) {
            return false;
        }

        if (! self::selectionAllows($preferences['reports'], [$catalog['report']])) {
            return false;
        }

        if ($catalog['kind'] === 'summary') {
            return self::selectionAllows($preferences['summaries'], [$catalog['cadence']]);
        }

        return self::selectionAllows($preferences['statuses'], [$catalog['status']]);
    }

    public static function systemAllows(string $alertType): bool
    {
        return self::matches($alertType, self::systemPreferences());
    }

    /**
     * @param  array{enabled?: bool, departments?: list<string>|null, statuses?: list<string>|null, reports?: list<string>|null, summaries?: list<string>|null}|null  $preferences
     * @return array{enabled: bool, departments: list<string>, statuses: list<string>, reports: list<string>, summaries: list<string>}
     */
    public static function normalize(?array $preferences): array
    {
        $preferences ??= [];

        return [
            'enabled' => (bool) ($preferences['enabled'] ?? false),
            'departments' => self::normalizeSelection(
                array_key_exists('departments', $preferences) ? $preferences['departments'] : null,
                array_keys(self::DEPARTMENTS),
            ),
            'statuses' => self::normalizeSelection(
                array_key_exists('statuses', $preferences) ? $preferences['statuses'] : null,
                array_keys(self::STATUSES),
            ),
            'reports' => self::normalizeSelection(
                array_key_exists('reports', $preferences) ? $preferences['reports'] : null,
                array_keys(self::REPORTS),
            ),
            'summaries' => self::normalizeSelection(
                array_key_exists('summaries', $preferences) ? $preferences['summaries'] : null,
                array_keys(self::SUMMARIES),
            ),
        ];
    }

    /**
     * Global switches from Settings → Email alerts.
     * Missing keys stay fully on so mail keeps flowing until an admin narrows it.
     *
     * @return array{enabled: bool, departments: list<string>, statuses: list<string>, reports: list<string>, summaries: list<string>, send_time: string, overdue_days: int, fallback_email: string}
     */
    public static function systemPreferences(): array
    {
        $stored = self::storedEmailAlertSettings();

        $normalized = self::normalize([
            'enabled' => $stored['enabled'] ?? true,
            'departments' => $stored['departments'] ?? null,
            'statuses' => $stored['statuses'] ?? null,
            'reports' => $stored['reports'] ?? null,
            'summaries' => $stored['summaries'] ?? null,
        ]);

        return array_merge($normalized, [
            'send_time' => self::normalizeTime($stored['send_time'] ?? '18:00'),
            'overdue_days' => min(30, max(1, (int) ($stored['overdue_days'] ?? 1))),
            'fallback_email' => trim((string) ($stored['fallback_email'] ?? config('quality.alert_email', ''))),
        ]);
    }

    /**
     * Expand stored selection for checkbox UI. "all" shows every particular as checked.
     *
     * @param  list<string>  $selected
     * @param  list<string>  $particulars
     * @return list<string>
     */
    public static function expandForUi(array $selected, array $particulars): array
    {
        if (in_array('all', $selected, true)) {
            return array_values($particulars);
        }

        return array_values(array_intersect($selected, $particulars));
    }

    /**
     * Collapse a UI multi-select back to storage form (all / particulars / empty).
     *
     * @param  list<string>  $selected
     * @param  list<string>  $particulars
     * @return list<string>
     */
    public static function collapseFromUi(array $selected, array $particulars): array
    {
        $selected = array_values(array_unique(array_map('strval', $selected)));
        $selected = array_values(array_intersect($selected, $particulars));

        if ($selected === []) {
            return [];
        }

        if (count($selected) === count($particulars)) {
            return ['all'];
        }

        return $selected;
    }

    /**
     * "All" is exclusive. Checking every particular collapses back to All.
     * Clearing the last box leaves the group empty, which sends nothing for that group.
     * With All on, clicking a particular means "everything except this".
     *
     * @param  list<string>  $selected
     * @param  list<string>  $particulars
     * @return list<string>
     */
    public static function toggle(array $selected, string $value, array $particulars): array
    {
        if ($value === 'all') {
            return in_array('all', $selected, true) ? [] : ['all'];
        }

        if (! in_array($value, $particulars, true)) {
            return $selected;
        }

        if (in_array('all', $selected, true)) {
            return array_values(array_diff($particulars, [$value]));
        }

        $selected = array_values(array_filter(
            $selected,
            fn (string $item) => $item !== 'all' && in_array($item, $particulars, true),
        ));

        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, fn (string $item) => $item !== $value));
        } else {
            $selected[] = $value;
        }

        if ($particulars !== [] && count($selected) === count($particulars)) {
            return ['all'];
        }

        return array_values($selected);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private static function translateMap(array $map): array
    {
        return collect($map)
            ->mapWithKeys(fn (string $label, string $key) => [$key => __($label)])
            ->all();
    }

    /**
     * @param  list<string>|string|null  $selection
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function normalizeSelection(mixed $selection, array $allowed): array
    {
        if ($selection === null) {
            return ['all'];
        }

        if (! is_array($selection)) {
            $selection = [$selection];
        }

        $selection = array_values(array_unique(array_map('strval', $selection)));

        if (in_array('all', $selection, true)) {
            return ['all'];
        }

        return array_values(array_intersect($selection, $allowed));
    }

    /**
     * @param  list<string>  $selection
     * @param  list<string|null>  $requiredAny
     */
    private static function selectionAllows(array $selection, array $requiredAny): bool
    {
        if (in_array('all', $selection, true)) {
            return true;
        }

        return count(array_intersect($selection, array_filter($requiredAny))) > 0;
    }

    public static function normalizeTime(mixed $value): string
    {
        $time = substr(trim((string) $value), 0, 5);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : '18:00';
    }

    /**
     * @return array<string, mixed>
     */
    private static function storedEmailAlertSettings(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return app(SettingService::class)->getGroup('email_alerts');
        } catch (\Throwable) {
            return [];
        }
    }
}
