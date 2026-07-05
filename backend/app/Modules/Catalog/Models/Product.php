<?php

namespace App\Modules\Catalog\Models;

use App\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class Product extends Model
{
    use HasFactory, HasTenant, HasUuids, SoftDeletes;

    // Valid product types (nature commerciale)
    public const TYPE_SIMPLE   = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const TYPE_SERVICE  = 'service';
    public const TYPE_KIT      = 'kit';
    public const TYPE_DIGITAL  = 'digital';   // RC-5B — produit immatériel (entitlement/licence)

    // Politique de suivi du stock (RC-5A) — comment le stock est compté
    public const STOCK_TRACKING_NONE       = 'none';        // service / digital : aucun stock
    public const STOCK_TRACKING_AGGREGATE  = 'aggregate';   // quantité agrégée (défaut historique)
    public const STOCK_TRACKING_BATCH      = 'batch';       // par lot (péremption)
    public const STOCK_TRACKING_SERIALIZED = 'serialized';  // par unité (IMEI/VIN)

    // Politique de livraison (RC-5A) — comment on remet le produit
    public const FULFILLMENT_NONE        = 'none';
    public const FULFILLMENT_MANUAL      = 'manual';
    public const FULFILLMENT_DELIVERY    = 'delivery';      // défaut physique
    public const FULFILLMENT_DOWNLOAD    = 'download';
    public const FULFILLMENT_LICENSE     = 'license';
    public const FULFILLMENT_APPOINTMENT = 'appointment';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'supplier_id',
        'sku',
        'name',
        'description',
        'price_amount',
        'price_currency',
        'compare_at_price_amount',
        'cost_amount',
        'status',
        'has_variants',
        'product_type',         // Sprint 17: simple|variable|service|kit|digital
        'stock_tracking',       // RC-5A: none|aggregate|batch|serialized
        'fulfillment_type',     // RC-5A: none|manual|delivery|download|license|appointment
        'warranty_policy_id',   // RC-5D: politique de garantie attachée (nullable)
        'barcode',
        'internal_barcode',
        'gtin',
        'barcode_type',
        'barcode_source',
        'barcode_auto_generated',
        'weight_kg',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'has_variants'           => 'boolean',
            'barcode_auto_generated' => 'boolean',
            'metadata'               => 'array',
            'weight_kg'              => 'decimal:3',
        ];
    }

    /**
     * Dérive la politique de stock/livraison à partir du type quand elle n'est pas explicitement
     * fournie — quel que soit le chemin de création (API, duplication, seeders, tests). Garantit
     * qu'un service/digital n'est JAMAIS stockable par accident (le défaut DB `aggregate` ne suffit pas).
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if ($product->stock_tracking === null) {
                $product->stock_tracking = self::defaultStockTrackingFor($product->product_type);
            }
            if ($product->fulfillment_type === null) {
                $product->fulfillment_type = self::defaultFulfillmentFor($product->product_type);
            }
        });
    }

    public static function defaultStockTrackingFor(?string $type): string
    {
        return match ($type) {
            self::TYPE_SERVICE, self::TYPE_DIGITAL => self::STOCK_TRACKING_NONE,
            default                                => self::STOCK_TRACKING_AGGREGATE,
        };
    }

    public static function defaultFulfillmentFor(?string $type): string
    {
        return match ($type) {
            self::TYPE_SERVICE => self::FULFILLMENT_MANUAL,
            self::TYPE_DIGITAL => self::FULFILLMENT_DOWNLOAD,
            default            => self::FULFILLMENT_DELIVERY,
        };
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

    public function isVariable(): bool
    {
        return $this->product_type === self::TYPE_VARIABLE || $this->has_variants;
    }

    public function isService(): bool
    {
        return $this->product_type === self::TYPE_SERVICE;
    }

    public function isDigital(): bool
    {
        return $this->product_type === self::TYPE_DIGITAL;
    }

    /**
     * Un produit est stockable s'il suit réellement du stock. Fait AUTORITÉ sur `stock_tracking`
     * (none → non stockable) tout en restant rétro-compatible : un service est non stockable même
     * si une donnée héritée porte encore `aggregate`.
     */
    public function isStockable(): bool
    {
        if ($this->product_type === self::TYPE_SERVICE || $this->product_type === self::TYPE_DIGITAL) {
            return false;
        }

        return ($this->stock_tracking ?? self::STOCK_TRACKING_AGGREGATE) !== self::STOCK_TRACKING_NONE;
    }

    public function isSerialized(): bool
    {
        return $this->stock_tracking === self::STOCK_TRACKING_SERIALIZED;
    }

    /** RC-5D — le produit déclenche-t-il une garantie à la vente ? */
    public function hasWarranty(): bool
    {
        return $this->warranty_policy_id !== null;
    }

    // â”€â”€ Money accessors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function price(): Money
    {
        return Money::of($this->price_amount, $this->price_currency);
    }

    public function compareAtPrice(): ?Money
    {
        if ($this->compare_at_price_amount === null) {
            return null;
        }

        return Money::of($this->compare_at_price_amount, $this->price_currency);
    }

    public function cost(): ?Money
    {
        if ($this->cost_amount === null) {
            return null;
        }

        return Money::of($this->cost_amount, $this->price_currency);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price_amount !== null
            && $this->compare_at_price_amount > $this->price_amount;
    }

    // â”€â”€ Status helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // â”€â”€ Relationships â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Suppliers\Models\Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** Normalized attribute axes for this product. */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('position');
    }

    /** Stock records across all warehouses for this product (no variant). */
    public function stocks(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Stock::class);
    }
}
