<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\EmailTemplateVersionFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_template_id
 * @property string $subject
 * @property string $body_markdown
 * @property int $version_number
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
final class EmailTemplateVersion extends Model
{
    /** @use HasFactory<EmailTemplateVersionFactory> */
    use HasFactory;

    protected static function newFactory(): EmailTemplateVersionFactory
    {
        return EmailTemplateVersionFactory::new();
    }

    public const UPDATED_AT = null;

    protected $fillable = [
        'email_template_id',
        'subject',
        'body_markdown',
        'version_number',
        'created_by',
    ];

    /**
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
