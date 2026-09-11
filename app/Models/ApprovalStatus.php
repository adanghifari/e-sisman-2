<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStatus extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['kode_status', 'nama_status'];

    public const PENDING = 'PENDING';

    public const WAITING = 'WAITING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const TERMINATED = 'TERMINATED';

    protected $table = 'm_approval_status';

    public $timestamps = false;

    public static function findByCode(string $code): self
    {
        return self::where('kode_status', $code)->firstOrFail();
    }
}

