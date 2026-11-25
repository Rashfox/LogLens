<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'status_code' => 'integer',
            'is_scanner' => 'boolean',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
        ];
    }
    public function scopeForNode($query, ?string $node)
    {
        $selfHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'log.rashfox.web.id';

        if ($node === $selfHost || $node === 'self_log') {
            return $query->where('log_source', 'self_log');
        }

        $query->where('log_source', 'access_log');

        if ($node && $node !== 'all-nodes') {
            return $query->where('server_node', $node);
        }

        return $query;
    }

    public function scopeForLogSource($query, ?string $source)
    {
        if ($source && in_array($source, ['access_log', 'self_log'], true)) {
            return $query->where('log_source', $source);
        }
        return $query;
    }
}
