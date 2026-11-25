<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'body_bytes' => 'integer',
            'request_time' => 'float',
            'upstream_response_time' => 'float',
            'is_blocked' => 'boolean',
            'logged_at' => 'datetime',
        ];
    }

    public function scopeStatusGroup($query, string $group)
    {
        return match ($group) {
            '2xx' => $query->whereBetween('status_code', [200, 299]),
            '3xx' => $query->whereBetween('status_code', [300, 399]),
            '4xx' => $query->whereBetween('status_code', [400, 499]),
            '5xx' => $query->whereBetween('status_code', [500, 599]),
            default => $query,
        };
    }

    public function scopeForNode($query, ?string $node)
    {
        if ($node === 'log.rashfox.web.id' || $node === 'self_log') {
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
    
    public function getStatusGroupAttribute(): string
    {
        return match (true) {
            $this->status_code >= 500 => '5xx',
            $this->status_code >= 400 => '4xx',
            $this->status_code >= 300 => '3xx',
            default => '2xx',
        };
    }
}
