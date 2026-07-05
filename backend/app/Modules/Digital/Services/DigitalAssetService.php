<?php

namespace App\Modules\Digital\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalAsset;
use App\Modules\Digital\Models\DigitalEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;

/**
 * RC-5I — fichiers privés des produits digitaux + liens de téléchargement signés.
 *
 * Le contenu n'est jamais servi par son chemin : le client obtient un **lien signé et expirable**
 * (route `digital.download`, hors auth) dérivé de son entitlement. La révocation prime même sur un lien
 * déjà émis (l'accessibilité de l'entitlement est revérifiée au téléchargement).
 */
class DigitalAssetService
{
    public const DISK = 'local';                 // disque privé (storage/app/private)
    private const LINK_TTL_MINUTES = 15;

    /** Stocke un fichier privé et l'attache à un produit digital. */
    public function attach(string $tenantId, Product $product, UploadedFile $file, ?string $userId = null): DigitalAsset
    {
        $path = $file->store("digital-assets/{$tenantId}", self::DISK);

        return DigitalAsset::create([
            'tenant_id'  => $tenantId,
            'product_id' => $product->id,
            // RC-9 F-7 — nom assaini (jamais réutiliser brut le nom client au download).
            'name'       => $this->sanitizeFilename($file->getClientOriginalName() ?: 'asset'),
            'disk'       => self::DISK,
            'path'       => $path,
            'size_bytes' => (int) ($file->getSize() ?: 0),
            // MIME dérivé du CONTENU (getMimeType), pas de la valeur client (getClientMimeType) spoofable.
            'mime'       => $file->getMimeType() ?: $file->getClientMimeType(),
            'checksum'   => @hash_file('sha256', $file->getRealPath()) ?: null,
            'version'    => 1,
            'is_active'  => true,
            'created_by' => $userId,
        ]);
    }

    /** Assainit un nom de fichier client (retire chemin et caractères douteux, borne la longueur). */
    private function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));            // retire tout composant de chemin
        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name);   // caractères sûrs seulement
        $name = preg_replace('/\.{2,}/', '.', (string) $name);      // pas de séquences « .. »
        $name = trim((string) $name, " ._");

        return $name === '' ? 'asset' : mb_substr($name, 0, 200);
    }

    /** Assets d'un produit (actifs par défaut). */
    public function forProduct(string $tenantId, string $productId, bool $activeOnly = true): Collection
    {
        return DigitalAsset::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->latest()
            ->get();
    }

    /**
     * Liens de téléchargement **signés et expirables** pour les assets actifs du produit d'un
     * entitlement accessible. Vide si l'entitlement est révoqué/expiré ou s'il n'y a pas d'asset.
     *
     * @return array<int,array{asset_id:string,name:string,size_bytes:int,url:string,expires_in:int}>
     */
    public function signedLinksFor(DigitalEntitlement $entitlement): array
    {
        if (! $entitlement->isAccessible()) {
            return [];
        }

        return $this->forProduct($entitlement->tenant_id, $entitlement->product_id)
            ->map(fn (DigitalAsset $a) => [
                'asset_id'   => $a->id,
                'name'       => $a->name,
                'size_bytes' => $a->size_bytes,
                'expires_in' => self::LINK_TTL_MINUTES * 60,
                'url'        => URL::temporarySignedRoute(
                    'digital.download',
                    now()->addMinutes(self::LINK_TTL_MINUTES),
                    ['token' => $entitlement->access_token, 'asset' => $a->id],
                ),
            ])
            ->all();
    }

    /** Asset actif appartenant au produit/tenant d'un entitlement (résolution au téléchargement). */
    public function findActiveAsset(string $tenantId, string $productId, string $assetId): ?DigitalAsset
    {
        return DigitalAsset::withoutTenantScope()
            ->where('id', $assetId)
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->first();
    }
}
