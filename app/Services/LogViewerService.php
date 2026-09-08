<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogViewerService
{
    public function logsPath(): string
    {
        return storage_path('logs');
    }

    /**
     * @return Collection<int, array{name: string, path: string, size: int, size_human: string, modified: int, modified_human: string}>
     */
    public function listFiles(): Collection
    {
        $path = $this->logsPath();

        if (! File::isDirectory($path)) {
            return collect();
        }

        return collect(File::files($path))
            ->filter(fn ($file) => Str::endsWith(Str::lower($file->getFilename()), '.log'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(function ($file) {
                $bytes = $file->getSize();

                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $bytes,
                    'size_human' => $this->humanBytes($bytes),
                    'modified' => $file->getMTime(),
                    'modified_human' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            });
    }

    public function resolveFile(?string $name): ?array
    {
        $files = $this->listFiles();

        if ($name) {
            $match = $files->firstWhere('name', $name);
            if ($match) {
                return $match;
            }
        }

        return $files->first();
    }

    /**
     * @return array{entries: LengthAwarePaginator, stats: array<string, int>}
     */
    public function paginate(
        string $absolutePath,
        string $search = '',
        string $level = '',
        int $perPage = 25,
        int $page = 1
    ): array {
        $entries = $this->parseFile($absolutePath);
        $stats = [
            'total' => $entries->count(),
            'emergency' => 0,
            'alert' => 0,
            'critical' => 0,
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
            'info' => 0,
            'debug' => 0,
        ];

        foreach ($entries as $entry) {
            $key = Str::lower($entry['level']);
            if (array_key_exists($key, $stats)) {
                $stats[$key]++;
            }
        }

        $filtered = $entries
            ->when($level !== '', fn (Collection $c) => $c->filter(
                fn ($e) => Str::lower($e['level']) === Str::lower($level)
            ))
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn ($e) => Str::contains(Str::lower($e['message'].' '.$e['context']), Str::lower($search))
            ))
            ->values();

        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $slice = $filtered->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'entries' => $paginator,
            'stats' => $stats,
        ];
    }

    /**
     * @return Collection<int, array{datetime: string, env: string, level: string, message: string, context: string}>
     */
    public function parseFile(string $absolutePath): Collection
    {
        if (! is_file($absolutePath) || ! $this->isSafeLogPath($absolutePath)) {
            return collect();
        }

        $content = @file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            return collect();
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:[\+\-]\d{2}:\d{2})?)\]\s+(\w+)\.(\w+):\s(.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'datetime' => $m[1],
                    'env' => $m[2],
                    'level' => Str::upper($m[3]),
                    'message' => $m[4],
                    'context' => '',
                ];
            } elseif ($current !== null) {
                $current['context'] .= ($current['context'] === '' ? '' : "\n").$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        // Newest first
        return collect(array_reverse($entries))->values();
    }

    public function download(string $name): BinaryFileResponse
    {
        $file = $this->resolveFile($name);

        abort_unless($file, 404);

        return response()->download($file['path'], $file['name']);
    }

    public function clear(string $name): void
    {
        $file = $this->resolveFile($name);
        abort_unless($file, 404);
        File::put($file['path'], '');
    }

    public function delete(string $name): void
    {
        $file = $this->resolveFile($name);
        abort_unless($file, 404);

        // Never delete today's laravel.log if it's the only active channel file — still allow delete of named files
        File::delete($file['path']);
    }

    public function isSafeLogPath(string $absolutePath): bool
    {
        $real = realpath($absolutePath);
        $base = realpath($this->logsPath());

        if ($real === false || $base === false) {
            return false;
        }

        return Str::startsWith($real, $base)
            && Str::endsWith(Str::lower($real), '.log');
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
