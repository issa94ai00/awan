<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteVisitor extends Model
{
    protected $table = 'site_visitors';

    protected $fillable = [
        'ip_address',
        'visit_count',
        'page_url',
        'user_agent',
        'visit_date',
        'visit_time',
    ];
}
