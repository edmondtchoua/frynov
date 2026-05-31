<?php

namespace App\Modules\ImportExport\Parsers;

/**
 * Normalizes file column headers to known system field names.
 * Supports multi-language aliases (FR + EN).
 */
class ColumnMapper
{
    /**
     * Aliases per entity type.
     * Key   = canonical system field name
     * Value = list of recognized header variations (normalized: lowercase, no accents, no spaces)
     */
    private static array $aliases = [
        'products' => [
            'sku'         => ['sku', 'reference', 'ref', 'codeproduit', 'productcode', 'code'],
            'name'        => ['nom', 'nomproduit', 'name', 'article', 'designation', 'libelle', 'productname'],
            'description' => ['description', 'desc', 'details'],
            'price'       => ['prix', 'price', 'prixvente', 'sellingprice', 'prixttc', 'prixht', 'tarif'],
            'cost'        => ['cout', 'cost', 'prixachat', 'purchaseprice', 'prixrevient'],
            'barcode'     => ['barcode', 'codebarre', 'ean', 'ean13', 'gtin', 'upc'],
            'category'    => ['categorie', 'category', 'famille', 'family', 'rayon'],
            'supplier'    => ['fournisseur', 'supplier', 'vendor', 'fourni'],
            'weight_kg'   => ['poids', 'weight', 'weightkg', 'poidskg', 'masse'],
            'status'      => ['statut', 'status', 'etat', 'actif', 'active'],
        ],
        'customers' => [
            'name'    => ['nom', 'name', 'client', 'raisonsociale', 'company', 'societe', 'nomdufournisseur'],
            'email'   => ['email', 'mail', 'adresseemail', 'emailaddress'],
            'phone'   => ['telephone', 'phone', 'tel', 'mobile', 'gsm', 'portable'],
            'address' => ['adresse', 'address', 'adresselivraison', 'localite'],
            'notes'   => ['notes', 'commentaire', 'comment', 'remarque', 'observation'],
        ],
        'suppliers' => [
            'name'          => ['nom', 'name', 'fournisseur', 'supplier', 'raisonsociale', 'societe'],
            'code'          => ['code', 'codefournisseur', 'suppliercode', 'vendorcode', 'reference'],
            'email'         => ['email', 'mail', 'adresseemail'],
            'phone'         => ['telephone', 'phone', 'tel', 'mobile', 'gsm'],
            'contact_name'  => ['contact', 'nomcontact', 'contactname', 'interlocuteur', 'responsable'],
            'payment_terms' => ['conditionspaiement', 'paymentterms', 'paiement', 'delai', 'echeance'],
            'notes'         => ['notes', 'commentaire', 'comment', 'remarque'],
            'status'        => ['statut', 'status', 'etat', 'actif'],
        ],
    ];

    /**
     * Auto-map an array of raw file headers to system field names.
     * Returns [ raw_header => system_field|null, ... ]
     */
    public static function autoMap(array $headers, string $entityType): array
    {
        $aliases = self::$aliases[$entityType] ?? [];
        $mapping = [];

        foreach ($headers as $header) {
            $normalized        = self::normalize($header);
            $mapping[$header]  = null;

            foreach ($aliases as $field => $variations) {
                if (in_array($normalized, $variations, true)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Apply a column mapping to a raw row.
     * Returns [ system_field => value, ... ] — only mapped columns included.
     */
    public static function applyMapping(array $rawRow, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $rawHeader => $systemField) {
            if ($systemField === null) {
                continue;
            }
            $mapped[$systemField] = $rawRow[$rawHeader] ?? null;
        }

        return $mapped;
    }

    /**
     * Normalize a header string: lowercase, remove accents, remove non-alphanumeric.
     * Uses iconv for accent stripping (no ext-intl required).
     */
    public static function normalize(string $header): string
    {
        $header = mb_strtolower(trim($header));

        // Remove accents via iconv transliteration (available in all PHP builds)
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if ($transliterated !== false) {
            $header = $transliterated;
        }

        // Keep only lowercase letters and digits
        return preg_replace('/[^a-z0-9]/', '', strtolower($header));
    }

    /**
     * Return known system fields for an entity type.
     */
    public static function knownFields(string $entityType): array
    {
        return array_keys(self::$aliases[$entityType] ?? []);
    }
}
