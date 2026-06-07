/**
 * In-memory bearer-token holder (security audit remediation).
 *
 * The Sanctum bearer token is NEVER persisted to localStorage/sessionStorage — an
 * XSS payload could otherwise exfiltrate it. The token lives only in memory for the
 * lifetime of the page; the auth store owns its value and the API client reads it.
 * A page reload therefore clears the session (sign back in) until the persistent
 * strategy (HttpOnly cookie / refresh token) is implemented.
 */
let token: string | null = null

export function setAuthToken(value: string | null): void {
  token = value
}

export function getAuthToken(): string | null {
  return token
}
