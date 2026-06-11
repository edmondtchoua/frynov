# API Warranties (Garanties — RC-5D)

Base URL : `/api/warranties`
Authentification : `Authorization: Bearer <token>` + appartenance tenant.
Écritures réservées aux rôles **manager / admin** (ou permission `catalog.manage`).

---

## GET /api/warranties/policies

Politiques de garantie du tenant. Query : `active_only=1` pour ne lister que les actives.

**Réponse 200**
```json
{
  "data": [
    { "id": "uuid", "name": "Garantie 12 mois", "duration_months": 12, "coverage": null, "is_active": true, "created_at": "..." }
  ]
}
```

---

## POST /api/warranties/policies  *(manager/admin)*

Crée une politique réutilisable.

**Corps**
```json
{ "name": "Garantie constructeur", "duration_months": 12, "coverage": "Pièces et main d'œuvre", "is_active": true }
```

| Champ | Règle |
|-------|-------|
| name | requis, ≤ 120 |
| duration_months | requis, entier 1–600 |
| coverage | optionnel, ≤ 2000 |
| is_active | optionnel (défaut `true`) |

**Réponse 201** : `{ "data": { ...policy } }`

---

## POST /api/warranties/products/{productId}/policy  *(manager/admin)*

Attache une politique à un produit (ou la détache avec `null`).

**Corps** : `{ "warranty_policy_id": "uuid|null" }`

**Réponses** : `200 { "data": { "id", "warranty_policy_id" } }` · `404` produit inconnu ·
`422` politique inexistante pour ce tenant.

---

## GET /api/warranties/orders/{orderId}

Contrats de garantie **générés à la vente** pour une commande. Vide tant que la commande n'est pas
livrée ou si aucun produit vendu n'avait de politique.

**Réponse 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "warranty_policy_id": "uuid",
      "product_id": "uuid",
      "inventory_unit_id": "uuid",
      "order_line_id": "uuid",
      "customer_id": "uuid",
      "serial_value": "359123456789012",
      "starts_at": "2026-06-12T00:00:00Z",
      "ends_at": "2027-06-12T00:00:00Z",
      "status": "active",
      "product_name": "iPhone 15",
      "policy_name": "Garantie 12 mois"
    }
  ],
  "count": 1
}
```

**Réponse 404** : commande inconnue pour ce tenant (isolation multi-tenant).

---

## SAV — réclamations (RC-5F)

### POST /api/warranties/contracts/{contractId}/claims  *(manager/admin)*

Ouvre une réclamation SAV. Refusée (**422**) si le contrat est **expiré** (sauf `override: true`) ou
**`void`** (toujours).

**Corps** : `{ "reason": "defect|breakage|malfunction|other", "description": "…", "override": false }`

**Réponse 201** : `{ "data": { ...claim, "status": "open", "out_of_warranty": false } }`

### GET /api/warranties/contracts/{contractId}/claims · GET /api/warranties/orders/{orderId}/claims

Réclamations d'un contrat / d'une commande (scopées tenant → 404 sinon).

### POST /api/warranties/claims/{id}/transition  *(manager/admin)*

Fait avancer une réclamation : `{ "status": "in_repair|resolved|replaced|rejected", "diagnostic": "…",
"resolution": "…", "resolution_note": "…" }`. **422** si la réclamation est déjà dans un statut terminal.

---

## Génération automatique

Les contrats ne se créent pas via l'API : ils sont **émis au `fulfill`** de la commande
(cf. [API Orders](orders.md)). Pour un produit **sérialisé**, un contrat est créé **par unité vendue**
(rattaché à l'IMEI/VIN) ; sinon, un contrat par ligne. La période = date de vente + `duration_months`.

---

## Notes

1. **Durée** en mois uniquement (V1).
2. **Idempotence** : réémettre pour une commande déjà couverte ne crée pas de doublon.
3. **Statuts** : `active` (défaut), `expired` (dérivable de `ends_at`), `void` (retour/annulation — à venir).
