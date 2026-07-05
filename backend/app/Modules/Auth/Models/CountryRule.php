<?php
namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CountryRule extends Model
{
    use HasUuids;
    protected $fillable = ["country_code","is_active","requires_approval","is_blocked","allowed_plans","default_currency","default_timezone","metadata"];
    protected function casts(): array {
        return ["is_active"=>"boolean","requires_approval"=>"boolean","is_blocked"=>"boolean","allowed_plans"=>"array","metadata"=>"array"];
    }
    public static function forCountry(string $code): ?static {
        return static::where("country_code", strtoupper($code))->first();
    }
}
