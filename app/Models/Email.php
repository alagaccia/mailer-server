<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $recipient
 * @property string $subject
 * @property string $body
 * @property array<int, array{filename?: string, content?: string, mime?: string}>|null $attachments
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['uuid', 'recipient', 'subject', 'body', 'attachments', 'status', 'attempts', 'last_error', 'sent_at'])]
class Email extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * Ogni email ha un uuid: se non viene fornito ne viene generato uno.
     */
    protected static function booted(): void
    {
        static::creating(function (self $email) {
            if (empty($email->uuid)) {
                $email->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, string|null>  $filters
     * @return Builder<self>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['recipient'] ?? null, fn (Builder $q, string $v) => $q->where('recipient', 'like', "%{$v}%"))
            ->when($filters['subject'] ?? null, fn (Builder $q, string $v) => $q->where('subject', 'like', "%{$v}%"))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '<=', $v.' 23:59:59'));
    }

    /**
     * Conteggio e dimensione totale degli allegati senza decodificare i base64.
     *
     * @return array{count: int, size: string}|null
     */
    public function attachmentsMeta(): ?array
    {
        if (empty($this->attachments)) {
            return null;
        }

        $bytes = 0;

        foreach ($this->attachments as $attachment) {
            $content = $attachment['content'] ?? '';
            $bytes += (int) (strlen($content) * 3 / 4) - substr_count(substr($content, -2), '=');
        }

        return [
            'count' => count($this->attachments),
            'size' => Number::fileSize($bytes, precision: 1),
        ];
    }
}
