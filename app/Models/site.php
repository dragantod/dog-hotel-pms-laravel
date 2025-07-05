<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Site extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'city',
        'postal_code',
        'country_code',
        'timezone',
        'tax_id',
        'company_id',
    ];

    /**
     * Get the company that owns the site.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the users that belong to the site.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site')
            ->withTimestamps();
    }
}
