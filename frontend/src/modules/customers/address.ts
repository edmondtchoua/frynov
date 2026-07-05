import type { CustomerAddress, CustomerAddressFields } from './types'

export function normalizeCustomerAddress(address: CustomerAddress | null | undefined): CustomerAddressFields {
  if (!address) {
    return { street: '', city: '', zip: '', country: '' }
  }

  if (typeof address === 'string') {
    return { street: address, city: '', zip: '', country: '' }
  }

  return {
    street: address.street ?? '',
    city: address.city ?? '',
    zip: address.zip ?? '',
    country: address.country ?? '',
  }
}

export function formatCustomerAddress(address: CustomerAddress | null | undefined): string {
  const normalized = normalizeCustomerAddress(address)

  return [normalized.street, normalized.zip, normalized.city, normalized.country]
    .filter(Boolean)
    .join(', ')
}
